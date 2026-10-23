<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Admin → Staf: akun staf panel admin beserta jabatannya. Khusus Owner. */
class StaffController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(Request $request): View
    {
        return view('admin.staff.index', [
            'staff' => User::staff()
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = $request->string('q')->trim()->value();
                    $q->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
                })
                ->when(StaffRole::tryFrom((string) $request->query('jabatan')), fn ($q, StaffRole $role) => $role === StaffRole::Owner
                    ? $q->where(fn ($q) => $q->where('staff_role', $role)->orWhereNull('staff_role'))
                    : $q->where('staff_role', $role))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'roles' => StaffRole::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.staff.form', ['member' => new User(['name' => '']), 'roles' => StaffRole::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $member = new User(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null, 'password' => $data['password']]);
        // role, staff_role & is_active tidak mass-assignable.
        $member->forceFill([
            'role' => UserRole::Admin,
            'staff_role' => StaffRole::from($data['staff_role']),
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->logger->log('staff.store', 'Menambah akun staf', $member, ['jabatan' => $member->staffRole()->label(), 'email' => $member->email]);

        return redirect()->route('admin.staff.index')->with('success', "Akun staf {$member->name} dibuat. Bagikan email & password ke yang bersangkutan.");
    }

    public function edit(User $staff): View
    {
        abort_unless($staff->isAdmin(), 404);

        return view('admin.staff.form', ['member' => $staff, 'roles' => StaffRole::cases()]);
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->isAdmin(), 404);

        $data = $this->validated($request, $staff);
        $oldRole = $staff->staffRole();
        $newRole = StaffRole::from($data['staff_role']);

        if ($oldRole === StaffRole::Owner && $newRole !== StaffRole::Owner) {
            $this->ensureAnotherOwner($staff, 'staff_role', $staff->is($request->user())
                ? 'Anda tidak bisa menurunkan jabatan sendiri karena tidak ada Owner lain yang aktif.'
                : 'Harus ada minimal satu Owner aktif.');
        }

        $staff->fill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
        if (filled($data['password'] ?? null)) {
            $staff->password = $data['password'];
        }
        $staff->forceFill(['staff_role' => $newRole]);

        $changes = collect($staff->getDirty())->except(['updated_at', 'password'])->keys()->all();
        $passwordChanged = $staff->isDirty('password');
        $staff->save();

        $this->logger->log('staff.update', 'Mengubah akun staf', $staff, array_filter([
            'jabatan' => $oldRole !== $newRole ? ['dari' => $oldRole?->label(), 'menjadi' => $newRole->label()] : null,
            'kolom' => array_values(array_diff($changes, ['staff_role'])) ?: null,
            'sandi_direset' => $passwordChanged ?: null,
        ]));

        return redirect()->route('admin.staff.index')->with('success', "Akun {$staff->name} diperbarui.");
    }

    public function toggle(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->isAdmin(), 404);

        if ($staff->is($request->user())) {
            return back()->withErrors(['staff' => 'Anda tidak bisa menonaktifkan akun sendiri.']);
        }

        if ($staff->is_active && $staff->isOwner()) {
            $this->ensureAnotherOwner($staff, 'staff', 'Harus ada minimal satu Owner aktif.');
        }

        $staff->forceFill(['is_active' => ! $staff->is_active])->save();

        $this->logger->log('staff.toggle', $staff->is_active ? 'Mengaktifkan akun staf' : 'Menonaktifkan akun staf', $staff);

        return back()->with('success', $staff->is_active ? "Akun {$staff->name} diaktifkan." : "Akun {$staff->name} dinonaktifkan dan tidak bisa login lagi.");
    }

    private function ensureAnotherOwner(User $except, string $field, string $message): void
    {
        $others = User::staff()
            ->where('is_active', true)
            ->whereKeyNot($except->getKey())
            ->where(fn ($q) => $q->where('staff_role', StaffRole::Owner)->orWhereNull('staff_role'))
            ->exists();

        if (! $others) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function validated(Request $request, ?User $member = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($member)],
            'phone' => ['nullable', 'string', 'max:20'],
            'staff_role' => ['required', new Enum(StaffRole::class)],
            'password' => [$member ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ], [
            'email.unique' => 'Email ini sudah dipakai akun lain (pelanggan atau staf).',
        ], ['staff_role' => 'jabatan', 'name' => 'nama', 'phone' => 'no. HP']);
    }
}
