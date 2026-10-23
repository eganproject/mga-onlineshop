<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Enums\StaffRole;
use App\Enums\StockMovementType;
use App\Jobs\ProcessProductImport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImport;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Catalog\Transfer\Spreadsheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
    }

    private function csv(array $rows, string $delimiter = ','): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row, $delimiter, escape: '');
        }
        rewind($handle);

        return stream_get_contents($handle);
    }

    private function upload(array $rows, string $type = 'products', ?string $mode = null, string $name = 'produk.csv'): ProductImport
    {
        $this->actingAs($this->admin)->post(route('admin.imports.store'), array_filter([
            'type' => $type,
            'mode' => $mode,
            'file' => UploadedFile::fake()->createWithContent($name, $this->csv($rows)),
        ]))->assertSessionHasNoErrors()->assertRedirect();

        return ProductImport::latest('id')->first();
    }

    private function commit(ProductImport $import): ProductImport
    {
        $this->actingAs($this->admin)->post(route('admin.imports.commit', $import))->assertRedirect(route('admin.imports.show', $import));

        return $import->refresh();
    }

    /** @return list<array<string, mixed>> */
    private function readDownload($response, string $format): array
    {
        $path = tempnam(sys_get_temp_dir(), 'test').'.'.$format;
        copy($response->baseResponse->getFile()->getPathname(), $path);

        return array_values(iterator_to_array(Spreadsheet::read($path, $format)));
    }

    private const HEADER = ['slug', 'nama_produk', 'kategori', 'deskripsi', 'sku_produk', 'aktif', 'unggulan', 'label_varian', 'nama_varian', 'sku_varian', 'varian_aktif', 'harga', 'harga_coret', 'berat_gram', 'stok', 'url_gambar'];

    private function row(array $values): array
    {
        return array_map(fn ($column) => $values[$column] ?? '', self::HEADER);
    }

    public function test_pages_require_admin(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('admin.products.transfer'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.products.export'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.products.transfer'))->assertOk()->assertSee('Impor produk');
    }

    public function test_warehouse_staff_can_only_use_stock_import(): void
    {
        $warehouse = User::factory()->staff(StaffRole::Warehouse)->create();
        $product = Product::factory()->create(['sku' => 'GDG-1', 'stock' => 2]);

        $this->actingAs($warehouse)->get(route('admin.products.transfer'))
            ->assertOk()->assertSee('Update stok massal')->assertDontSee('Template Excel');
        $this->actingAs($warehouse)->get(route('admin.products.export'))->assertForbidden();
        $this->actingAs($warehouse)->get(route('admin.stock.export'))->assertOk();

        $this->actingAs($warehouse)->post(route('admin.imports.store'), [
            'type' => 'products',
            'file' => UploadedFile::fake()->createWithContent('p.csv', $this->csv([['nama_produk', 'harga'], ['X', '1']])),
        ])->assertForbidden();

        $this->actingAs($warehouse)->post(route('admin.imports.store'), [
            'type' => 'stock', 'mode' => 'add',
            'file' => UploadedFile::fake()->createWithContent('s.csv', $this->csv([['sku', 'stok'], ['GDG-1', '3']])),
        ])->assertRedirect();
        $import = ProductImport::latest('id')->first();
        $this->actingAs($warehouse)->post(route('admin.imports.commit', $import))->assertRedirect();
        $this->assertSame(5, $product->refresh()->stock);

        // Impor produk milik admin lain tidak bisa dibuka staf gudang.
        $productImport = $this->upload([['nama_produk', 'harga'], ['Rahasia', '1000']]);
        $this->actingAs($warehouse)->get(route('admin.imports.show', $productImport))->assertForbidden();
    }

    public function test_templates_can_be_downloaded_and_read_back(): void
    {
        foreach (['xlsx', 'csv'] as $format) {
            $response = $this->actingAs($this->admin)->get(route('admin.products.template', ['format' => $format]))->assertOk();
            $this->assertStringContainsString("template-impor-produk.{$format}", $response->headers->get('content-disposition'));

            $rows = $this->readDownload($response, $format);
            $this->assertCount(3, $rows);
            $this->assertSame('Kabel Data USB-C 1m', $rows[0]['nama_produk']);
            $this->assertSame('Hitam', $rows[1]['nama_varian']);
        }
    }

    public function test_import_validates_first_then_creates_products_variants_categories_and_stock(): void
    {
        $import = $this->upload([
            self::HEADER,
            $this->row(['nama_produk' => 'Kabel Type C', 'kategori' => 'Kabel & Charger > Kabel Data', 'sku_produk' => 'KBL-C', 'harga' => 'Rp 35.000', 'harga_coret' => '50.000', 'berat_gram' => '80', 'stok' => '25', 'unggulan' => 'ya']),
            $this->row(['nama_produk' => 'Casing Clear', 'kategori' => 'Casing', 'label_varian' => 'Warna', 'nama_varian' => 'Hitam', 'sku_varian' => 'CC-H', 'harga' => '45000', 'stok' => '10']),
            $this->row(['nama_produk' => 'Casing Clear', 'nama_varian' => 'Putih', 'sku_varian' => 'CC-P', 'harga' => '40000', 'stok' => '7', 'varian_aktif' => 'tidak']),
        ]);

        // Tahap pratinjau belum mengubah data.
        $this->assertSame(ImportStatus::Ready, $import->status);
        $this->assertSame(3, $import->total_rows);
        $this->assertSame(0, $import->error_rows);
        $this->assertSame(2, $import->summary['create']);
        $this->assertSame(['Kabel & Charger > Kabel Data', 'Casing'], $import->summary['new_categories']);
        $this->assertSame(0, Product::count());

        $this->actingAs($this->admin)->get(route('admin.imports.show', $import))
            ->assertOk()->assertSee('Impor sekarang')->assertSee('Kabel Type C');

        $import = $this->commit($import);
        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(2, $import->result['created']);
        $this->assertNull($import->path);

        $cable = Product::firstWhere('sku', 'KBL-C');
        $this->assertSame(35000, $cable->price);
        $this->assertSame(50000, $cable->compare_at_price);
        $this->assertSame(25, $cable->stock);
        $this->assertTrue($cable->is_featured);
        $this->assertSame('kabel-type-c', $cable->slug);
        $this->assertSame('Kabel Data', $cable->category->name);
        $this->assertSame('Kabel & Charger', $cable->category->parent->name);

        $case = Product::firstWhere('name', 'Casing Clear');
        $this->assertTrue($case->has_variants);
        $this->assertSame('Warna', $case->variant_label);
        $this->assertCount(2, $case->variants);
        $this->assertSame(10, $case->stock, 'Varian nonaktif tidak dihitung.');
        $this->assertFalse($case->variants->firstWhere('sku', 'CC-P')->is_active);

        $movement = StockMovement::where('product_id', $cable->id)->sole();
        $this->assertSame(StockMovementType::Initial, $movement->type);
        $this->assertSame($this->admin->id, $movement->user_id);
        $this->assertSame(3, StockMovement::count());
    }

    public function test_row_errors_are_reported_and_valid_products_still_import(): void
    {
        Product::factory()->create(['sku' => 'DIPAKAI']);

        $import = $this->upload([
            self::HEADER,
            $this->row(['nama_produk' => 'Tanpa Harga', 'stok' => '5']),
            $this->row(['nama_produk' => 'Stok Salah', 'harga' => '1000', 'stok' => 'banyak']),
            $this->row(['nama_produk' => 'Produk Valid', 'harga' => '1000', 'stok' => '3']),
            $this->row(['nama_produk' => 'SKU Bentrok', 'sku_produk' => 'BARU', 'harga' => '1000']),
            $this->row(['nama_produk' => 'SKU Bentrok 2', 'sku_produk' => 'BARU', 'harga' => '1000']),
            $this->row(['nama_produk' => 'Varian Dobel', 'nama_varian' => 'Hitam', 'harga' => '1000']),
            $this->row(['nama_produk' => 'Varian Dobel', 'nama_varian' => 'hitam', 'harga' => '1000']),
            $this->row(['slug' => 'tidak-ada', 'harga' => '1000']),
            $this->row(['nama_produk' => 'Kategori Dalam', 'kategori' => 'A > B > C', 'harga' => '1000']),
        ]);

        $this->assertSame(ImportStatus::Ready, $import->status);
        $this->assertSame(9, $import->total_rows);
        $this->assertSame(7, $import->error_rows);

        $messages = collect($import->errors)->keyBy('row')->map->message;
        $this->assertStringContainsString('Harga wajib diisi', $messages[2]);
        $this->assertStringContainsString('Stok harus berupa angka', $messages[3]);
        $this->assertStringContainsString('SKU produk BARU dipakai lebih dari satu', $messages[6]);
        $this->assertStringContainsString('muncul dua kali', $messages[8]);
        $this->assertStringContainsString('tidak ditemukan', $messages[9]);
        $this->assertStringContainsString('maksimal 2 tingkat', $messages[10]);

        $this->commit($import);

        $this->assertTrue(Product::where('name', 'Produk Valid')->exists());
        $this->assertTrue(Product::where('name', 'SKU Bentrok')->exists());
        $this->assertFalse(Product::where('name', 'Tanpa Harga')->exists());
        $this->assertFalse(Product::where('name', 'Varian Dobel')->exists());
        $this->assertSame(3, Product::count());
    }

    public function test_existing_products_are_updated_and_blank_cells_keep_old_values(): void
    {
        $category = Category::factory()->create(['name' => 'Aksesoris']);
        $product = Product::factory()->create(['slug' => 'powerbank-10k', 'name' => 'Powerbank 10K', 'price' => 200000, 'stock' => 10, 'category_id' => $category->id, 'description' => 'Lama', 'weight' => 300]);

        $import = $this->upload([
            self::HEADER,
            $this->row(['slug' => 'powerbank-10k', 'harga' => '185.000', 'stok' => '4']),
        ]);
        $this->assertSame(1, $import->summary['update']);

        $this->commit($import);
        $product->refresh();

        $this->assertSame(185000, $product->price);
        $this->assertSame(4, $product->stock);
        $this->assertSame('Powerbank 10K', $product->name);
        $this->assertSame('Lama', $product->description);
        $this->assertSame(300, $product->weight);
        $this->assertSame($category->id, $product->category_id);

        $movement = StockMovement::where('product_id', $product->id)->sole();
        $this->assertSame(StockMovementType::Adjustment, $movement->type);
        $this->assertSame(-6, $movement->quantity);
        $this->assertStringContainsString('Impor produk', $movement->note);
    }

    public function test_variant_rows_update_existing_variants_and_add_new_ones(): void
    {
        $product = Product::factory()->create(['slug' => 'casing-a']);
        $black = ProductVariant::factory()->for($product)->create(['name' => 'Hitam', 'sku' => 'CA-H', 'stock' => 5]);
        $product->refresh();

        $this->commit($this->upload([
            self::HEADER,
            $this->row(['slug' => 'casing-a', 'sku_varian' => 'CA-H', 'stok' => '9', 'harga' => '30000']),
            $this->row(['slug' => 'casing-a', 'nama_varian' => 'Biru', 'harga' => '32000', 'stok' => '2']),
        ]));

        $this->assertSame(9, $black->refresh()->stock);
        $this->assertSame(30000, $black->price);
        $this->assertSame(['Hitam', 'Biru'], $product->variants()->pluck('name')->all());
        $this->assertSame(11, $product->refresh()->stock);
        $this->assertSame(30000, $product->price);
    }

    public function test_variant_mode_mismatch_is_rejected(): void
    {
        Product::factory()->create(['slug' => 'polos']);

        $import = $this->upload([
            self::HEADER,
            $this->row(['slug' => 'polos', 'nama_varian' => 'Merah', 'harga' => '1000']),
        ]);

        $this->assertSame(1, $import->error_rows);
        $this->assertStringContainsString('tidak memakai varian', $import->errors[0]['message']);
        $this->assertFalse($import->canCommit());
        $this->actingAs($this->admin)->post(route('admin.imports.commit', $import))->assertSessionHas('error');
    }

    public function test_exported_file_can_be_reimported_without_duplicates_or_stock_changes(): void
    {
        $category = Category::factory()->create(['name' => 'Earphone']);
        Product::factory()->create(['name' => 'TWS Pro', 'category_id' => $category->id, 'stock' => 8, 'compare_at_price' => 999000]);
        $withVariants = Product::factory()->create(['name' => 'Strap Watch']);
        ProductVariant::factory()->for($withVariants)->count(2)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.products.export', ['format' => 'csv']))->assertOk();
        $rows = $this->readDownload($response, 'csv');
        $this->assertCount(3, $rows);
        $this->assertSame('Earphone', $rows[0]['kategori']);

        $path = $response->baseResponse->getFile()->getPathname();
        $this->actingAs($this->admin)->post(route('admin.imports.store'), [
            'type' => 'products',
            'file' => UploadedFile::fake()->createWithContent('ekspor.csv', file_get_contents($path)),
        ]);
        $import = ProductImport::latest('id')->first();

        $this->assertSame(0, $import->error_rows, json_encode($import->errors));
        $this->assertSame(2, $import->summary['update']);
        $this->assertSame(0, $import->summary['create']);

        $movements = StockMovement::count();
        $this->commit($import);
        $this->assertSame(2, Product::count());
        $this->assertSame(2, ProductVariant::count());
        $this->assertSame($movements, StockMovement::count());
    }

    public function test_xlsx_export_contains_variants_and_reimports(): void
    {
        $product = Product::factory()->create(['name' => '=HYPERLINK("x")']);
        ProductVariant::factory()->for($product)->create(['name' => 'Merah', 'sku' => 'M-1']);

        $response = $this->actingAs($this->admin)->get(route('admin.products.export'))->assertOk();
        $rows = $this->readDownload($response, 'xlsx');

        $this->assertSame('=HYPERLINK("x")', $rows[0]['nama_produk'], 'Teks ditulis sebagai teks, bukan rumus.');
        $this->assertSame('M-1', $rows[0]['sku_varian']);
    }

    public function test_csv_export_escapes_formulas(): void
    {
        Product::factory()->create(['name' => '=1+1']);

        $response = $this->actingAs($this->admin)->get(route('admin.products.export', ['format' => 'csv']))->assertOk();

        $this->assertStringContainsString("'=1+1", file_get_contents($response->baseResponse->getFile()->getPathname()));
    }

    public function test_bulk_stock_update_set_mode_logs_movements(): void
    {
        $simple = Product::factory()->create(['sku' => 'SMP-1', 'stock' => 10]);
        $parent = Product::factory()->create(['slug' => 'induk']);
        $variant = ProductVariant::factory()->for($parent)->create(['name' => 'Hijau', 'sku' => null, 'stock' => 3]);

        $import = $this->upload([
            ['sku', 'slug', 'nama_produk', 'nama_varian', 'stok', 'catatan'],
            ['SMP-1', '', 'Simple', '', '15', 'Opname Oktober'],
            ['', 'induk', '', 'hijau', '0', ''],
            ['TIDAK-ADA', '', '', '', '5', ''],
        ], 'stock', 'set');

        $this->assertSame(ImportStatus::Ready, $import->status);
        $this->assertSame(1, $import->error_rows);
        $this->assertSame(2, $import->summary['changed']);
        $this->assertSame(10, $simple->refresh()->stock, 'Belum berubah sebelum dikonfirmasi.');

        $import = $this->commit($import);
        $this->assertSame(2, $import->result['changed']);

        $this->assertSame(15, $simple->refresh()->stock);
        $this->assertSame(0, $variant->refresh()->stock);
        $this->assertSame(0, $parent->refresh()->stock);

        $movement = StockMovement::where('product_id', $simple->id)->sole();
        $this->assertSame('Opname Oktober', $movement->note);
        $this->assertSame(StockMovementType::Adjustment, $movement->type);
        $this->assertSame($this->admin->id, $movement->user_id);
    }

    public function test_bulk_stock_update_add_mode_rejects_negative_stock(): void
    {
        $a = Product::factory()->create(['sku' => 'A', 'stock' => 5]);
        $b = Product::factory()->create(['sku' => 'B', 'stock' => 5]);

        $import = $this->upload([
            ['sku', 'stok'],
            ['A', '+20'],
            ['B', '-8'],
            ['A', '1'],
        ], 'stock', 'add');

        $messages = collect($import->errors)->pluck('message', 'row');
        $this->assertStringContainsString('tidak bisa dikurangi 8', $messages[3]);
        $this->assertStringContainsString('sudah ada di baris 2', $messages[4]);

        $this->commit($import);
        $this->assertSame(25, $a->refresh()->stock);
        $this->assertSame(5, $b->refresh()->stock);
        $this->assertSame(StockMovementType::Restock, StockMovement::where('product_id', $a->id)->sole()->type);
    }

    public function test_stock_export_can_be_used_as_stock_import(): void
    {
        $product = Product::factory()->create(['stock' => 4]);

        $response = $this->actingAs($this->admin)->get(route('admin.stock.export', ['format' => 'csv']))->assertOk();
        $rows = $this->readDownload($response, 'csv');
        $this->assertSame($product->sku, $rows[0]['sku']);
        $this->assertSame(4, (int) $rows[0]['stok']);

        $import = $this->upload([['sku', 'stok'], [$product->sku, 4]], 'stock', 'set');
        $this->assertSame(0, $import->summary['changed']);
        $this->assertFalse($import->error_rows > 0);
    }

    public function test_missing_required_columns_fails_the_import(): void
    {
        $import = $this->upload([['harga', 'stok'], ['1000', '1']]);

        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertStringContainsString('nama_produk', $import->failure);
    }

    public function test_semicolon_csv_and_indonesian_headers_are_accepted(): void
    {
        $content = $this->csv([
            ['Nama Produk*', 'Harga (Rp)', 'Stok', 'Berat (gram)'],
            ['Holder Mobil', '75.000', '6', '250'],
        ], ';');

        $this->actingAs($this->admin)->post(route('admin.imports.store'), [
            'type' => 'products',
            'file' => UploadedFile::fake()->createWithContent('data.csv', "\xEF\xBB\xBF".$content),
        ]);
        $this->commit(ProductImport::latest('id')->first());

        $product = Product::firstWhere('name', 'Holder Mobil');
        $this->assertSame(75000, $product->price);
        $this->assertSame(250, $product->weight);
        $this->assertSame(6, $product->stock);
    }

    public function test_large_files_are_processed_on_the_queue(): void
    {
        Queue::fake();

        $rows = [['nama_produk', 'harga']];
        foreach (range(1, 305) as $i) {
            $rows[] = ["Produk {$i}", '1000'];
        }
        $import = $this->upload($rows);

        $this->assertSame(ImportStatus::Validating, $import->status);
        Queue::assertPushed(ProcessProductImport::class, fn ($job) => $job->import->is($import) && ! $job->commit);

        $this->actingAs($this->admin)->get(route('admin.imports.show', $import))->assertOk()->assertSee('Memeriksa isi file');
    }

    public function test_queued_job_validates_and_imports_large_file(): void
    {
        $rows = [['nama_produk', 'harga', 'stok']];
        foreach (range(1, 305) as $i) {
            $rows[] = ["Produk {$i}", '1000', '1'];
        }
        // Antrean "sync" di test: job langsung jalan.
        $import = $this->commit($this->upload($rows));

        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(305, Product::count());
    }

    public function test_import_cannot_be_committed_twice(): void
    {
        $import = $this->upload([['nama_produk', 'harga'], ['Sekali Saja', '1000']]);
        $this->commit($import);

        $this->actingAs($this->admin)->post(route('admin.imports.commit', $import))->assertSessionHas('error');
        $this->assertSame(1, Product::count());
    }

    public function test_reimporting_same_new_products_updates_by_name_instead_of_duplicating(): void
    {
        $rows = [['nama_produk', 'harga', 'stok'], ['Tempered Glass', '15000', '10']];

        $this->commit($this->upload($rows));
        $second = $this->upload($rows);

        $this->assertSame(1, $second->summary['update']);
        $this->commit($second);
        $this->assertSame(1, Product::count());
    }

    public function test_images_are_downloaded_from_public_urls_only(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Http::fake([
            '93.184.216.34/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            '*' => Http::response('bukan gambar', 200),
        ]);

        $import = $this->commit($this->upload([
            ['nama_produk', 'harga', 'url_gambar'],
            ['Foto OK', '1000', 'https://93.184.216.34/a.png | https://93.184.216.35/teks.txt'],
            ['Foto Lokal', '1000', 'http://127.0.0.1/rahasia.png'],
        ]));

        $product = Product::firstWhere('name', 'Foto OK');
        $this->assertSame(1, $product->images()->count(), 'File teks tidak disimpan sebagai foto.');
        $image = ProductImage::where('product_id', $product->id)->first();
        Storage::disk('public')->assertExists($image->path);

        $this->assertSame(0, Product::firstWhere('name', 'Foto Lokal')->images()->count());
        $warnings = collect($import->result['warnings'])->pluck('message')->implode(' ');
        $this->assertStringContainsString('alamat tidak diizinkan', $warnings);
        $this->assertStringContainsString('bukan gambar', $warnings);
    }

    public function test_upload_rejects_other_file_types(): void
    {
        $this->actingAs($this->admin)->post(route('admin.imports.store'), [
            'type' => 'products',
            'file' => UploadedFile::fake()->create('produk.pdf', 10),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, ProductImport::count());
    }

    public function test_finished_imports_can_be_deleted_and_are_pruned(): void
    {
        $import = $this->upload([['nama_produk', 'harga'], ['Hapus', '1000']]);
        $path = $import->path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->admin)->delete(route('admin.imports.destroy', $import))->assertRedirect(route('admin.products.transfer'));
        $this->assertModelMissing($import);
        Storage::disk('local')->assertMissing($path);

        $old = $this->upload([['nama_produk', 'harga'], ['Lama', '1000']]);
        $old->forceFill(['created_at' => now()->subDays(31)])->save();
        $this->artisan('model:prune', ['--model' => ProductImport::class])->assertSuccessful();
        $this->assertModelMissing($old);
    }
}
