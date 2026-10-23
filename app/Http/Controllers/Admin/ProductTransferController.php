<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessProductImport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Services\Catalog\Transfer\ProductExporter;
use App\Services\Catalog\Transfer\Spreadsheet;
use App\Services\Catalog\Transfer\StockImporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Impor & ekspor katalog lewat file Excel (.xlsx) / CSV. */
class ProductTransferController extends Controller
{
    /** File dengan baris lebih dari ini diproses di antrean agar request tidak timeout. */
    public const QUEUE_AFTER_ROWS = 300;

    public const MAX_UPLOAD_KB = 10240;

    public function __construct(private readonly ProductExporter $exporter) {}

    public function index(Request $request): View
    {
        $types = $this->allowedTypes($request);
        abort_if($types === [], 403);

        return view('admin.products.transfer', [
            'canProducts' => in_array(ImportType::Products, $types, true),
            'canStock' => in_array(ImportType::Stock, $types, true),
            'imports' => ProductImport::with('user')->whereIn('type', $types)->latest('id')->limit(10)->get(),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'parent_id']),
            'modes' => StockImporter::MODES,
            'maxUploadKb' => self::MAX_UPLOAD_KB,
        ]);
    }

    public function exportProducts(Request $request): BinaryFileResponse
    {
        $format = $this->format($request);
        $query = Product::query()
            ->when($request->filled('kategori'), function (Builder $q) use ($request) {
                $category = Category::find($request->integer('kategori'));
                $q->whereIn('category_id', $category ? $category->familyIds() : [0]);
            })
            ->when($request->query('status') === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'nonaktif', fn ($q) => $q->where('is_active', false));

        return $this->download('produk_'.now()->format('Y-m-d_His'), $format, $this->exporter->products($query));
    }

    public function exportStock(Request $request): BinaryFileResponse
    {
        return $this->download('stok_'.now()->format('Y-m-d_His'), $this->format($request), $this->exporter->stock(Product::query()));
    }

    public function template(Request $request): BinaryFileResponse
    {
        return $this->download('template-impor-produk', $this->format($request), $this->exporter->productTemplate());
    }

    /** Unggah file, lalu periksa isinya (langsung untuk file kecil, antrean untuk file besar). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(ImportType::class)],
            'mode' => ['required_if:type,stock', 'nullable', Rule::in(array_keys(StockImporter::MODES))],
            'file' => ['required', 'file', 'extensions:xlsx,csv', 'max:'.self::MAX_UPLOAD_KB],
        ], [
            'file.extensions' => 'File harus berformat .xlsx atau .csv.',
            'file.max' => 'Ukuran file maksimal '.(self::MAX_UPLOAD_KB / 1024).' MB.',
        ], ['file' => 'file', 'mode' => 'mode update stok']);

        $type = ImportType::from($data['type']);
        abort_unless(in_array($type, $this->allowedTypes($request), true), 403);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $import = new ProductImport;
        $import->forceFill([
            'user_id' => $request->user()->id,
            'type' => $type,
            'mode' => $type === ImportType::Stock ? $data['mode'] : null,
            'status' => ImportStatus::Validating,
            'filename' => Str::limit($file->getClientOriginalName(), 200, ''),
            'path' => $file->storeAs('imports', Str::uuid().'.'.$extension, ProductImport::DISK),
        ])->save();

        $this->process($import, false);

        return redirect()->route('admin.imports.show', $import);
    }

    public function show(Request $request, ProductImport $import): View
    {
        $this->authorizeImport($request, $import);

        if ($import->isStale()) {
            ProcessProductImport::importer($import)->fail($import, 'Proses impor terlalu lama dan dihentikan. Silakan unggah ulang.');
        }

        return view('admin.products.import', [
            'import' => $import->load('user'),
            'modes' => StockImporter::MODES,
        ]);
    }

    public function commit(Request $request, ProductImport $import): RedirectResponse
    {
        $this->authorizeImport($request, $import);

        // Ubah status secara atomik supaya klik ganda tidak mengimpor dua kali.
        $claimed = ProductImport::whereKey($import->id)
            ->where('status', ImportStatus::Ready)
            ->where('total_rows', '>', 0)
            ->whereColumn('error_rows', '<', 'total_rows')
            ->update(['status' => ImportStatus::Importing, 'updated_at' => now()]);

        if (! $claimed) {
            return back()->with('error', 'Impor ini tidak bisa dijalankan (sudah diproses atau tidak ada baris valid).');
        }

        $this->process($import->refresh(), true);

        return redirect()->route('admin.imports.show', $import);
    }

    public function destroy(Request $request, ProductImport $import): RedirectResponse
    {
        $this->authorizeImport($request, $import);

        if ($import->status->isRunning() && ! $import->isStale()) {
            return back()->with('error', 'Impor masih diproses. Tunggu sampai selesai.');
        }

        $import->delete();

        return redirect()->route('admin.products.transfer')->with('success', 'Riwayat impor dihapus.');
    }

    /** Impor produk butuh hak kelola katalog, update stok butuh hak kelola stok. */
    private function allowedTypes(Request $request): array
    {
        return array_values(array_filter([
            $request->user()->can('catalog.manage') ? ImportType::Products : null,
            $request->user()->can('stock.manage') ? ImportType::Stock : null,
        ]));
    }

    private function authorizeImport(Request $request, ProductImport $import): void
    {
        abort_unless(in_array($import->type, $this->allowedTypes($request), true), 403);
    }

    private function process(ProductImport $import, bool $commit): void
    {
        $rows = $commit ? $import->total_rows : $this->countRows($import);
        $job = new ProcessProductImport($import, $commit);

        $rows > self::QUEUE_AFTER_ROWS ? dispatch($job) : dispatch_sync($job);
    }

    private function countRows(ProductImport $import): int
    {
        $count = 0;

        try {
            $format = pathinfo($import->path, PATHINFO_EXTENSION);
            foreach (Spreadsheet::read($import->filePath(), $format) as $row) {
                if (++$count > self::QUEUE_AFTER_ROWS) {
                    break;
                }
            }
        } catch (\Throwable) {
            // File rusak: biarkan validasi yang melaporkan errornya.
        }

        return $count;
    }

    private function format(Request $request): string
    {
        return in_array($request->query('format'), Spreadsheet::FORMATS, true) ? $request->query('format') : 'xlsx';
    }

    private function download(string $name, string $format, array $sheets): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'export');
        Spreadsheet::write($path, $format, $sheets);

        return response()->download($path, "{$name}.{$format}", [
            'Content-Type' => $format === 'csv'
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
