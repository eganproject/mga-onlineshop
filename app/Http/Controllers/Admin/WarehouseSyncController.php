<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStockItem;
use App\Models\WarehouseSyncRun;
use App\Services\Warehouse\WarehouseApiException;
use App\Services\Warehouse\WarehouseClient;
use App\Services\Warehouse\WarehouseStockSync;
use App\Support\AdminList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Stok › Sinkron gudang: status tarikan dari warehouse29, tombol sinkron
 * manual, dan menghubungkan SKU gudang ke produk/varian toko.
 */
class WarehouseSyncController extends Controller
{
    public function __construct(private readonly WarehouseStockSync $sync) {}

    public function index(Request $request, WarehouseClient $client): View
    {
        $unlinkedCount = WarehouseStockItem::unlinked()->where('status', 'active')->count();
        $tab = in_array($request->query('tab'), ['belum', 'terhubung'], true)
            ? $request->query('tab')
            : ($unlinkedCount > 0 ? 'belum' : 'terhubung');
        $term = $request->string('q')->trim()->value();
        $search = fn (Builder $q) => $q->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
            ->where('sku', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")));

        $query = $tab === 'belum'
            ? WarehouseStockItem::unlinked()->where('status', 'active')
            : WarehouseStockItem::whereNotIn('id', WarehouseStockItem::unlinked()->select('id'));
        $items = AdminList::paginate($search($query)->orderBy('sku'), $request, 25, key: 'warehouse-'.$tab);

        $options = $this->linkOptions();

        return view('admin.stock.warehouse', [
            'configured' => $client->isConfigured(),
            'enabled' => (bool) config('services.warehouse.enabled'),
            'interval' => (int) config('services.warehouse.interval', 5),
            'host' => parse_url((string) $client->baseUrl(), PHP_URL_HOST),
            'lastRun' => WarehouseSyncRun::latest('id')->first(),
            'lastSuccess' => WarehouseSyncRun::where('status', WarehouseSyncRun::STATUS_SUCCESS)->latest('id')->first(),
            'runs' => WarehouseSyncRun::with('user')->latest('id')->limit(8)->get(),
            'tab' => $tab,
            'items' => $items,
            'counts' => [
                'items' => WarehouseStockItem::where('status', 'active')->count(),
                'unlinked' => $unlinkedCount,
                'linked' => WarehouseStockItem::whereNotIn('id', WarehouseStockItem::unlinked()->select('id'))->count(),
            ],
            'targets' => $tab === 'terhubung' ? $this->sync->targets($items->pluck('sku')->all()) : [],
            'reserved' => $tab === 'terhubung' ? $this->sync->reservedQuantities() : [],
            'options' => $options,
            'suggestions' => $tab === 'belum' ? $this->suggestions($items->getCollection(), $options) : [],
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        try {
            $run = $this->sync->run($request->boolean('full'), $request->user());
        } catch (WarehouseApiException $e) {
            return back()->with('warning', $e->getMessage());
        }

        if ($run->status !== WarehouseSyncRun::STATUS_SUCCESS) {
            return back()->with('error', 'Sinkron gagal: '.$run->message);
        }

        return back()->with('success', "Sinkron selesai: {$run->fetched} SKU diterima dari gudang, {$run->updated} stok diubah.");
    }

    public function link(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:100'],
            'target' => ['required', 'string', 'regex:/^[pv]:\d+$/'],
        ], ['target.required' => 'Pilih produk atau varian toko.'], ['target' => 'produk toko']);

        $item = WarehouseStockItem::where('sku', $data['sku'])->firstOrFail();
        [$type, $id] = explode(':', $data['target']);

        $model = $type === 'v'
            ? ProductVariant::with('product')->whereHas('product', fn ($q) => $q->where('has_variants', true))->find($id)
            : Product::without('coverImage')->where('has_variants', false)->find($id);

        if (! $model) {
            return back()->with('error', 'Produk/varian tidak ditemukan atau memakai varian. Pilih variannya.');
        }

        $taken = Product::where('warehouse_sku', $item->sku)->exists() || ProductVariant::where('warehouse_sku', $item->sku)->exists();

        if ($taken) {
            return back()->with('error', "SKU gudang {$item->sku} sudah terhubung ke produk lain. Putuskan dulu di tab Terhubung.");
        }

        $model->forceFill(['warehouse_sku' => $item->sku])->save();
        $this->sync->apply([$item->sku]);
        $model->refresh();

        return back()->with('success', "SKU {$item->sku} terhubung ke {$this->label($model)}. Stok toko sekarang {$model->stock}.");
    }

    public function unlink(Request $request): RedirectResponse
    {
        $sku = (string) $request->validate(['sku' => ['required', 'string', 'max:100']])['sku'];

        $count = Product::where('warehouse_sku', $sku)->update(['warehouse_sku' => null])
            + ProductVariant::where('warehouse_sku', $sku)->update(['warehouse_sku' => null]);

        return back()->with($count ? 'success' : 'warning', $count
            ? "SKU gudang {$sku} diputus. Stok produk tidak diubah lagi oleh sinkron."
            : "SKU {$sku} tidak terhubung lewat pilihan manual (cocok karena SKU toko sama). Ubah SKU produknya untuk memutus.");
    }

    /**
     * Produk tanpa varian & varian yang belum punya SKU gudang, untuk pilihan "Hubungkan".
     *
     * @return array<string, string> "p:1" / "v:5" => label
     */
    private function linkOptions(): array
    {
        $products = Product::without('coverImage')->where('has_variants', false)->whereNull('warehouse_sku')
            ->orderBy('name')->get(['id', 'name', 'sku'])
            ->mapWithKeys(fn (Product $p) => ['p:'.$p->id => $p->name.($p->sku ? " ({$p->sku})" : '')]);

        $variants = ProductVariant::query()->whereNull('product_variants.warehouse_sku')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.has_variants', true)
            ->orderBy('products.name')->orderBy('product_variants.sort_order')
            ->get(['product_variants.id', 'product_variants.name', 'product_variants.sku', 'products.name as product_name'])
            ->mapWithKeys(fn ($v) => ['v:'.$v->id => "{$v->product_name} — {$v->name}".($v->sku ? " ({$v->sku})" : '')]);

        return $products->merge($variants)->sortBy(fn ($label) => mb_strtolower($label))->all();
    }

    /**
     * Saran pasangan berdasarkan nama yang sama persis (abaikan huruf besar & tanda baca).
     *
     * @return array<string, string> SKU gudang => "p:1" / "v:5"
     */
    private function suggestions($items, array $options): array
    {
        $normalize = fn (?string $name) => trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii((string) $name))));
        $byName = [];

        foreach ($options as $value => $label) {
            // Label opsi berisi "(SKU)" di belakang; buang sebelum dibandingkan.
            $byName[$normalize(preg_replace('/\s\([^()]*\)$/', '', $label))] ??= $value;
        }

        return $items->mapWithKeys(fn (WarehouseStockItem $item) => [$item->sku => $byName[$normalize($item->name)] ?? null])
            ->filter()
            ->all();
    }

    private function label(Product|ProductVariant $model): string
    {
        return $model instanceof ProductVariant ? "{$model->product->name} ({$model->name})" : $model->name;
    }
}
