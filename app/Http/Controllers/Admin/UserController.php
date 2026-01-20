<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\LogsAdminActivity;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    use LogsAdminActivity;
    /**
     * Display user list
     */
    public function index(Request $request)
    {
        $query = User::with('area:id,name');

        // Filter by role
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Filter by active status
        if ($request->has('active_only')) {
            $query->where('is_active', true);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $query->orderBy('name');

        $users = $query->paginate($request->get('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/User/Index', [
            'users' => $users,
            'filters' => $request->only(['role', 'active_only', 'search']),
            'roles' => [
                'admin' => 'Administrator',
                'penagih' => 'Penagih/Collector',
                'technician' => 'Teknisi',
                'finance' => 'Finance',
            ],
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        return Inertia::render('Admin/User/Form', [
            'user' => null,
            'areas' => Area::active()->get(['id', 'name']),
            'roles' => [
                'admin' => 'Administrator',
                'penagih' => 'Penagih/Collector',
                'technician' => 'Teknisi',
                'finance' => 'Finance',
            ],
        ]);
    }

    /**
     * Store new user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => ['required', Password::defaults()],
            'role' => 'required|in:admin,penagih,technician,finance',
            'area_id' => 'nullable|exists:areas,id',
            'is_active' => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $user = User::create($validated);

        // Log audit
        $this->auditCreate(AdminAuditLog::MODULE_USER, $user);

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil ditambahkan');
    }

    /**
     * Show user detail
     */
    public function show(User $user)
    {
        $user->load('area');

        // If collector, get their stats
        $stats = null;
        if ($user->role === 'penagih') {
            $thisMonth = now()->startOfMonth();

            $stats = [
                'assigned_customers' => $user->assignedCustomers()->count(),
                'collections_this_month' => $user->collectedPayments()
                    ->where('created_at', '>=', $thisMonth)
                    ->sum('amount'),
                'collection_count_this_month' => $user->collectedPayments()
                    ->where('created_at', '>=', $thisMonth)
                    ->count(),
                'expenses_this_month' => $user->expenses()
                    ->where('expense_date', '>=', $thisMonth)
                    ->where('status', 'approved')
                    ->sum('amount'),
            ];
        }

        return Inertia::render('Admin/User/Show', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(User $user)
    {
        return Inertia::render('Admin/User/Form', [
            'user' => $user,
            'areas' => Area::active()->get(['id', 'name']),
            'roles' => [
                'admin' => 'Administrator',
                'penagih' => 'Penagih/Collector',
                'technician' => 'Teknisi',
                'finance' => 'Finance',
            ],
        ]);
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'password' => ['nullable', Password::defaults()],
            'role' => 'required|in:admin,penagih,technician,finance',
            'area_id' => 'nullable|exists:areas,id',
            'is_active' => 'boolean',
        ]);

        // Store old values for audit
        $oldValues = $user->only(['name', 'email', 'phone', 'role', 'area_id', 'is_active']);

        // Only update password if provided
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        // Log audit
        $this->auditUpdate(AdminAuditLog::MODULE_USER, $user, $oldValues);

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil diperbarui');
    }

    /**
     * Delete user
     */
    public function destroy(User $user)
    {
        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri');
        }

        // Check if user has associated data
        if ($user->role === 'penagih') {
            if ($user->collectedPayments()->exists()) {
                return back()->with('error', 'User memiliki riwayat penagihan, tidak dapat dihapus');
            }
            if ($user->assignedCustomers()->exists()) {
                return back()->with('error', 'User masih memiliki pelanggan yang ditugaskan');
            }
        }

        // Log audit before delete
        $this->auditDelete(AdminAuditLog::MODULE_USER, $user);

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil dihapus');
    }

    /**
     * Toggle user active status
     */
    public function toggleActive(User $user)
    {
        // Prevent self-deactivation
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menonaktifkan akun sendiri');
        }

        $oldStatus = $user->is_active;
        $user->update(['is_active' => !$user->is_active]);

        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        // Log audit
        $this->auditAction(
            AdminAuditLog::MODULE_USER,
            AdminAuditLog::ACTION_TOGGLE_STATUS,
            "User {$user->name} {$status}",
            $user,
            ['is_active' => $oldStatus],
            ['is_active' => $user->is_active]
        );

        return back()->with('success', "User berhasil {$status}");
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => ['required', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Log audit
        $this->auditAction(
            AdminAuditLog::MODULE_USER,
            AdminAuditLog::ACTION_PASSWORD_RESET,
            "Reset password user {$user->name}",
            $user
        );

        return back()->with('success', 'Password berhasil direset');
    }

    /**
     * Get collectors list (for dropdowns)
     */
    public function collectors()
    {
        $collectors = User::where('role', 'penagih')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'area_id']);

        return response()->json($collectors);
    }
}
