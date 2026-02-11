<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LoginController extends Controller
{
    /**
     * Tampilkan form login
     */
    public function showLoginForm()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Username atau email wajib diisi',
            'password.required' => 'Password wajib diisi',
        ]);

        $username = $request->input('username');
        $password = $request->input('password');

        // Cari user berdasarkan email atau username (phone)
        $user = User::where('email', $username)
            ->orWhere('phone', $username)
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'username' => ['Username atau email tidak ditemukan'],
            ]);
        }

        // Cek apakah user aktif
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['Akun Anda tidak aktif. Hubungi administrator.'],
            ]);
        }

        // Verifikasi password
        if (!Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Password yang Anda masukkan salah'],
            ]);
        }

        // Login user
        Auth::login($user, $request->boolean('remember'));

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Log audit - only for admin roles
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_FINANCE])) {
            AdminAuditLog::log(
                AdminAuditLog::MODULE_AUTH,
                AdminAuditLog::ACTION_LOGIN,
                "Login ke sistem",
                $user,
                null,
                ['last_login_at' => now()->toDateTimeString()],
                null,
                $user->id
            );
        }

        // Regenerate session
        $request->session()->regenerate();

        // Redirect berdasarkan role
        return $this->redirectBasedOnRole($user);
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        $user = Auth::user();

        // Log audit before logout - only for admin roles
        if ($user && in_array($user->role, [User::ROLE_ADMIN, User::ROLE_FINANCE])) {
            AdminAuditLog::log(
                AdminAuditLog::MODULE_AUTH,
                AdminAuditLog::ACTION_LOGOUT,
                "Logout dari sistem",
                $user,
                null,
                null,
                null,
                $user->id
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Clear intended URL agar tidak redirect ke halaman sebelumnya
        $request->session()->forget('url.intended');

        return redirect()->route('login');
    }

    /**
     * Redirect user berdasarkan role
     */
    protected function redirectBasedOnRole(User $user)
    {
        return match ($user->role) {
            User::ROLE_ADMIN, User::ROLE_FINANCE => redirect()->intended(route('admin.dashboard')),
            User::ROLE_COLLECTOR => redirect()->intended(route('collector.dashboard')),
            User::ROLE_TECHNICIAN => redirect()->intended(route('admin.dashboard')),
            default => redirect()->route('login'),
        };
    }
}
