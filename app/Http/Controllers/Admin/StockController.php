<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Catalog\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $products = Product::with(['variants', 'category'])
            ->search($request->string('q')->trim()->value() ?: null)
            ->when($status === 'menipis', fn ($q) => $stockFilter($q, '<=', $threshold))
            ->when($status === 'habis', fn ($q) => $stockFilter($q, '=', 0))
            ->orderBy('stock')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.stock.index', [
            'products' => $products,
            'threshold' => $threshold,
            'summary' => [
                'units' => (int) Product::sum('stock'),
                'out' => Product::where('is_active', true)->where('stock', 0)->count(),
                'low' => Product::where('is_active', true)->where('stock', '>', 0)->where('stock', '<=', $threshold)->count(),
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

        return view('admin.stock.history', [
            'movements' => StockMovement::with(['product', 'user', 'order'])
                ->when($product, fn ($q) => $q->where('product_id', $product->id))
                ->when($type, fn ($q) => $q->where('type', $type))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'product' => $product,
            'type' => $type,
        ]);
    }
}
