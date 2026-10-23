<?php

namespace App\Http\Controllers\Account;

use App\Contracts\ShippingProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class AddressController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.addresses.index', [
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('account.addresses.form', ['address' => new Address(['label' => 'Rumah'])]);
    }

    public function store(AddressRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        // Alamat pertama otomatis jadi alamat utama.
        $data['is_default'] = ($data['is_default'] ?? false) || ! $user->addresses()->exists();

        DB::transaction(function () use ($user, $data) {
            if ($data['is_default']) {
                $user->addresses()->update(['is_default' => false]);
            }
            $user->addresses()->create($data);
        });

        return redirect($request->input('redirect_to') === 'checkout' ? route('checkout.index') : route('account.addresses.index'))
            ->with('success', 'Alamat berhasil disimpan.');
    }

    public function edit(Request $request, Address $address): View
    {
        $this->authorizeOwner($request, $address);

        return view('account.addresses.form', ['address' => $address]);
    }

    public function update(AddressRequest $request, Address $address): RedirectResponse
    {
        $this->authorizeOwner($request, $address);
        $data = $request->validated();
        $data['is_default'] = ($data['is_default'] ?? false) || $address->is_default;

        DB::transaction(function () use ($request, $address, $data) {
            if ($data['is_default']) {
                $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }
            $address->update($data);
        });

        return redirect()->route('account.addresses.index')->with('success', 'Alamat berhasil diperbarui.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        $this->authorizeOwner($request, $address);

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $request->user()->addresses()->latest()->first()?->update(['is_default' => true]);
        }

        return back()->with('success', 'Alamat dihapus.');
    }

    public function makeDefault(Request $request, Address $address): RedirectResponse
    {
        $this->authorizeOwner($request, $address);

        DB::transaction(function () use ($request, $address) {
            $request->user()->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return back()->with('success', 'Alamat utama diperbarui.');
    }

    /** Pencarian lokasi tujuan (kecamatan) untuk autocomplete form alamat. */
    public function destinations(Request $request, ShippingProvider $shipping): JsonResponse
    {
        $keyword = (string) $request->string('q')->trim()->limit(50, '');

        try {
            return response()->json(['data' => $shipping->searchDestinations($keyword)]);
        } catch (Throwable $e) {
            Log::warning('Gagal mencari lokasi tujuan', ['error' => $e->getMessage()]);

            return response()->json(['data' => [], 'message' => 'Pencarian lokasi sedang tidak tersedia.'], 503);
        }
    }

    private function authorizeOwner(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 404);
    }
}
