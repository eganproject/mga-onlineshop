<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Catalog\StockService;
use App\Support\AdminList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(private readonly StockService $stock) {}

    /** Daftar stok per produk & varian, dengan form penyesuaian cepat. */
    public function index(Request $request): View
    {
        $threshold = config('shop.low_stock_threshold');
        $status = $request->query('status');

        // Kondisi stok dicek di level varian untuk produk bervarian.
        $stockFilter = fn ($q, string $op, int $value) => $q->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('has_variants', false)->where('stock', $op, $value))
            ->orWhereHas('variants', fn ($v) => $v->where('is_active', true)->where('stock', $op, $value)));

        $query = Product::with(['variants', 'category'])
            ->search($request->string('q')->trim()->value() ?: null)
            ->when($status === 'menipis', fn ($q) => $stockFilter($q, '<=', $threshold))
            ->when($status === 'habis', fn ($q) => $stockFilter($q, '=', 0));

        AdminList::sort($query, $request, [
            'stok' => fn ($q, $dir) => $q->orderBy('stock', $dir)->orderBy('name'),
            'nama' => 'name',
        ], 'stok');

        $products = AdminList::paginate($query, $request, 25);

        return view('admin.stock.index', [
            'products' => $products,
            'threshold' => $threshold,
            // Dihitung sama seperti filter "menipis"/"habis": produk bervarian dicek per varian.
            'summary' => [
                'units' => (int) Product::sum('stock'),
                'out' => $stockFilter(Product::where('is_active', true), '=', 0)->count(),
                'low' => $stockFilter(Product::where('is_active', true), '<=', $threshold)->count(),
            ],
            'types' => StockMovementType::manual(),
        ]);
    }

    public function adjust(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer'],
            'mode' => ['required', Rule::in(['add', 'subtract', 'set'])],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'type' => ['required', Rule::enum(StockMovementType::class)->only(StockMovementType::manual())],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['quantity' => 'jumlah', 'note' => 'catatan']);

        $product = Product::findOrFail($data['product_id']);
        $variant = $product->has_variants ? $product->variants()->findOrFail($data['variant_id'] ?? 0) : null;
        $type = StockMovementType::from($data['type']);
        $name = $variant ? "{$product->name} ({$variant->name})" : $product->name;

        try {
            $movement = match ($data['mode']) {
                'add' => $this->stock->adjust($product, $variant, $data['quantity'], $type, $data['note'] ?? null),
                'subtract' => $this->stock->adjust($product, $variant, -$data['quantity'], $type, $data['note'] ?? null),
                'set' => $this->stock->set($product, $variant, $data['quantity'], $type, $data['note'] ?? null),
            };
        } catch (InsufficientStockException $e) {
            return back()->with('error', "Stok {$name} hanya {$e->available}, tidak bisa dikurangi sebanyak itu.");
        }

        if (! $movement) {
            return back()->with('success', "Stok {$name} tidak berubah.");
        }

        return back()->with('success', "Stok {$name} sekarang {$movement->stock_after}.");
    }

    public function history(Request $request): View
    {
        $product = $request->filled('produk') ? Product::find($request->integer('produk')) : null;
        $type = StockMovementType::tryFrom((string) $request->query('jenis'));
        $from = $this->date($request->query('from'));
        $to = $this->date($request->query('to'));
        $term = $request->string('q')->trim()->value();

        return view('admin.stock.history', [
            'movements' => AdminList::paginate(StockMovement::with(['product', 'user', 'order'])
                ->when($product, fn ($q) => $q->where('product_id', $product->id))
                ->when($type, fn ($q) => $q->where('type', $type))
                ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('variant_name', 'like', "%{$term}%")
                    ->orWhere('note', 'like', "%{$term}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"))))
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from->startOfDay()))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to->endOfDay()))
                ->latest('id'), $request, 25),
            'product' => $product,
            'type' => $type,
        ]);
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
