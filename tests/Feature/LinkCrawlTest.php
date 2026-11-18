<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CrawlsLinks;
use Tests\TestCase;

/**
 * Menjelajahi semua link internal (seperti pengunjung yang mengklik) untuk tiap peran,
 * memakai data demo lengkap. Setiap link yang tampil harus bisa dibuka: tidak boleh 404, 403, atau 500,
 * dan setiap form/aksi yang tampil harus terdaftar & diizinkan untuk user itu.
 */
class LinkCrawlTest extends TestCase
{
    private function cashPayment(array $overrides = []): array
    {
        return $overrides + ['method' => 'cash', 'paid_at' => now()->format('Y-m-d\\TH:i')];
    }

    use CrawlsLinks;
    use RefreshDatabase;

    private string $manualBillUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->createManualOrders();
    }

    /**
     * Data demo belum punya penjualan manual: buat lewat form admin (seperti CS), supaya halaman
     * pesanan manual, pembeli tanpa akun, dan link tagihan publik ikut dijelajahi.
     */
    private function createManualOrders(): void
    {
        $cs = User::where('email', 'cs@mga.test')->firstOrFail();
        $products = Product::where('is_active', true)->where('has_variants', false)->where('stock', '>', 5)->take(3)->get();
        $this->assertCount(3, $products, 'Data demo harus punya produk aktif tanpa varian.');

        $this->actingAs($cs);
        // Urutan: tanpa akun (tagihan QRIS), akun pelanggan (lunas tunai), akun pelanggan (belum dibayar).
        foreach ([['Pembeli Tanpa Akun', null], ['Pelanggan Demo', 'pelanggan@mga.test'], ['Pelanggan Demo', 'pelanggan@mga.test']] as $i => [$name, $email]) {
            $this->post(route('admin.orders.store'), [
                'items' => [['product_id' => $products[$i]->id, 'quantity' => 1]],
                'customer_name' => $name,
                'customer_email' => $email,
                'customer_phone' => '081234567890',
                'shipping_mode' => 'pickup',
            ])->assertSessionHasNoErrors();
        }

        [$guest, $linked] = Order::where('source', 'manual')->orderBy('id')->take(2)->get()->all();
        $this->post(route('admin.orders.payment.choose', $guest), ['method' => 'qris'])->assertSessionHasNoErrors();
        $this->post(route('admin.orders.offline-payment', $linked), $this->cashPayment(['method' => 'bank_transfer', 'reference' => 'REF-123', 'proof' => UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf')]))->assertSessionHasNoErrors();

        $this->manualBillUrl = $guest->refresh()->billUrl();
        auth()->logout();
        $this->flushSession();
    }

    public function test_manual_order_bill_link_is_not_broken(): void
    {
        // Halaman tagihan berdiri sendiri (tanpa menu), jadi ikut jelajahi toko dari beranda.
        $this->assertNoBrokenLinks(null, [$this->manualBillUrl, '/']);
    }

    public function test_guest_links_are_not_broken(): void
    {
        $this->fillCart();
        $this->assertNoBrokenLinks(null, ['/', '/produk', '/artikel', '/masuk', '/daftar', '/keranjang', '/sitemap.xml']);
    }

    public function test_customer_links_are_not_broken(): void
    {
        // Seperti setelah `shop:import-mga --replace`: produk yang pernah dibeli & di-wishlist dinonaktifkan.
        $customer = User::where('email', 'pelanggan@mga.test')->firstOrFail();
        $ordered = Product::whereIn('id', OrderItem::whereIn('order_id', $customer->orders()->select('id'))->select('product_id'))->first();
        $this->assertNotNull($ordered, 'Data demo pelanggan harus punya pesanan.');
        $ordered->forceFill(['is_active' => false])->save();
        $customer->wishlist()->syncWithoutDetaching([$ordered->id]);

        $this->fillCart();
        $this->assertNoBrokenLinks($customer, ['/', '/produk', '/akun', '/keranjang', '/artikel', '/checkout']);
    }

    public function test_superadmin_links_are_not_broken(): void
    {
        $this->fillCart();
        $this->assertNoBrokenLinks(User::where('email', 'admin@mga.test')->firstOrFail(), ['/admin', '/']);
    }

    public function test_warehouse_staff_links_are_not_broken(): void
    {
        $this->assertNoBrokenLinks(User::where('email', 'gudang@mga.test')->firstOrFail(), ['/admin']);
    }

    public function test_customer_service_links_are_not_broken(): void
    {
        $this->assertNoBrokenLinks(User::where('email', 'cs@mga.test')->firstOrFail(), ['/admin']);
    }

    /**
     * Izin jabatan bisa dikombinasikan bebas di Admin › Jabatan & Akses, jadi tiap izin dicek sendirian
     * (plus jabatan tanpa izin sama sekali): link & tombol menu lain tidak boleh muncul tanpa izinnya.
     */
    #[DataProvider('singlePermissions')]
    public function test_role_with_single_permission_has_no_broken_links(?string $permission): void
    {
        $this->crawlPagesPerShape = 2;
        $this->crawlQueryVariants = 1;
        $this->crawlMaxPages = 250;

        $role = new Role(['name' => 'Uji '.($permission ?? 'kosong'), 'permissions' => $permission ? Permission::normalize([$permission]) : []]);
        $role->forceFill(['key' => Role::makeKey('uji '.($permission ?? 'kosong'))])->save();

        $staff = User::create(['name' => 'Staf Uji', 'email' => 'uji@mga.test', 'password' => 'password']);
        $staff->forceFill(['role' => UserRole::Admin, 'is_active' => true, 'staff_role' => $role->key, 'email_verified_at' => now()])->save();

        if ($permission === null) {
            $this->actingAs($staff->fresh())->get('/admin')->assertOk()->assertSee('belum punya hak akses');
        }

        $this->assertNoBrokenLinks($staff->fresh(), ['/admin', '/admin/profile']);
    }

    /** @return array<string, array{0: ?string}> */
    public static function singlePermissions(): array
    {
        $cases = ['tanpa izin' => [null]];
        foreach (Permission::cases() as $permission) {
            $cases[$permission->value] = [$permission->value];
        }

        return $cases;
    }

    /** Isi keranjang (session) supaya tombol Checkout & langkah lanjutannya ikut dijelajahi. */
    private function fillCart(): void
    {
        $product = Product::where('is_active', true)->where('has_variants', false)->where('stock', '>', 0)->where('min_order_qty', '<=', 1)->firstOrFail();
        $this->withSession(['cart' => [(string) $product->id => 1]]);
    }

    /** @param  list<string>  $start */
    private function assertNoBrokenLinks(?User $user, array $start): void
    {
        $broken = $this->crawlForBrokenLinks($user, $start);

        $this->assertSame([], $broken, 'Link/aksi rusak ditemukan ('.($user?->email ?? 'tamu').'):'."\n".implode("\n", $broken));
    }
}
