<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Billing\InvoiceRenderer;
use App\Services\Orders\OrderService;
use App\Services\Shipping\TrackingService;
use App\Support\AdminList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = OrderStatus::tryFrom((string) $request->query('status'));

        $query = $this->filtered($request)
            ->with('user')
            ->withCount('items')
            ->when($status, fn ($q) => $q->where('status', $status));

        AdminList::sort($query, $request, [
            'tanggal' => fn ($q, $dir) => $q->orderBy('created_at', $dir)->orderBy('id', $dir),
            'total' => 'total',
        ], '-tanggal');

        $orders = AdminList::paginate($query, $request);

        // Jumlah per status mengikuti filter lain (pencarian, tanggal, kurir).
        $counts = $this->filtered($request)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $couriers = Order::whereNotNull('courier')->distinct()->orderBy('courier')->pluck('courier');

        return view('admin.orders.index', compact('orders', 'status', 'counts', 'couriers'));
    }

    public function show(Order $order, TrackingService $tracking): View
    {
        return view('admin.orders.show', [
            'order' => $order->load('items.product', 'user', 'payments', 'statusHistories.user'),
            'canTrack' => $tracking->canTrack($order),
        ]);
    }

    public function updateStatus(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'tracking_number' => [
                Rule::requiredIf($request->input('status') === OrderStatus::Shipped->value),
                'nullable', 'string', 'max:64',
            ],
            'note' => [
                Rule::requiredIf($request->input('status') === OrderStatus::Cancelled->value),
                'nullable', 'string', 'max:250',
            ],
        ], [
            'tracking_number.required' => 'Nomor resi wajib diisi saat pesanan dikirim.',
            'note.required' => 'Tulis alasan pembatalan.',
        ]);

        try {
            $orders->transition(
                $order,
                OrderStatus::from($data['status']),
                isset($data['tracking_number']) ? trim($data['tracking_number']) : null,
                $data['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Status pesanan diperbarui menjadi '.$order->refresh()->status->label().'.');
    }

    public function updateTracking(Request $request, Order $order, OrderService $orders, TrackingService $tracking): RedirectResponse
    {
        $data = $request->validate(['tracking_number' => ['required', 'string', 'max:64']]);

        try {
            $tracking->forget($order);
            $orders->updateTracking($order, trim($data['tracking_number']));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Nomor resi diperbarui.');
    }

    /** Potongan HTML hasil lacak resi, dimuat lewat fetch di halaman detail. */
    public function tracking(Request $request, Order $order, TrackingService $tracking): View
    {
        return view('partials.order-tracking', [
            'order' => $order,
            'result' => $tracking->track($order, fresh: $request->boolean('fresh')),
            'refreshUrl' => route('admin.orders.tracking', [$order, 'fresh' => 1]),
        ]);
    }

    public function invoice(Order $order, InvoiceRenderer $invoices): View
    {
        return view('orders.invoice', $invoices->data($order) + ['pdfUrl' => route('admin.orders.invoice.pdf', $order)]);
    }

    public function invoicePdf(Order $order, InvoiceRenderer $invoices): Response
    {
        return $invoices->pdf($order);
    }

    public function packingSlip(Order $order): View
    {
        return view('orders.packing-slip', ['orders' => collect([$order->load('items')])]);
    }

    /** Aksi massal dari daftar pesanan: proses atau cetak packing slip. */
    public function bulk(Request $request, OrderService $orders): RedirectResponse|View
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['process', 'packing-slips'])],
            'orders' => ['required', 'array', 'max:100'],
            'orders.*' => ['integer'],
        ], ['orders.required' => 'Pilih minimal satu pesanan.']);

        $selected = Order::whereKey($data['orders'])->with('items')->oldest()->get();

        if ($data['action'] === 'packing-slips') {
            return view('orders.packing-slip', ['orders' => $selected]);
        }

        Gate::authorize('orders.manage');

        $processed = 0;

        foreach ($selected as $order) {
            if ($order->status === OrderStatus::Paid) {
                $orders->transition($order, OrderStatus::Processing);
                $processed++;
            }
        }

        $skipped = $selected->count() - $processed;

        return back()->with('success', "{$processed} pesanan diproses.".($skipped ? " {$skipped} dilewati karena statusnya bukan Dibayar." : ''));
    }

    private function filtered(Request $request): Builder
    {
        $from = $this->date($request->query('from'));
        $to = $this->date($request->query('to'));

        return Order::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->trim()->value();
                $q->where(fn ($q) => $q->where('code', 'like', "%{$term}%")
                    ->orWhere('recipient_name', 'like', "%{$term}%")
                    ->orWhere('recipient_phone', 'like', "%{$term}%")
                    ->orWhere('tracking_number', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$term}%")));
            })
            ->when($request->filled('courier'), fn ($q) => $q->where('courier', $request->string('courier')->value()))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->endOfDay()));
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $value) ?: null;
    }
}
