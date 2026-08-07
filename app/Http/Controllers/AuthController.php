<?php

namespace App\Http\Controllers;

use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    // GET /  -> login page
    public function showLoginForm(Request $request)
    {
        if (session('loggedin')) {
            return redirect()->route('dashboard');
        }

        // Avoid bfcache / stale CSRF tokens causing intermittent 419s
        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    // POST /login
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = $request->input('username');
        $password = $request->input('password');

        $user = UserDetail::where('username', $username)
            ->where('active', 'Y')
            ->first();

        if (!$user) {
            return back()->withInput()->with('error', 'Invalid username or account not active');
        }

        // Check if account is currently locked
        if ($user->account_locked && $user->locked_until && now()->lt($user->locked_until)) {
            $minutesLeft = now()->diffInMinutes($user->locked_until);
            return back()->withInput()->with('error', "Account is locked. Try again in {$minutesLeft} minute(s).");
        }

        // Auto-unlock if lock period has passed
        if ($user->account_locked && $user->locked_until && now()->gte($user->locked_until)) {
            $user->account_locked = false;
            $user->failed_login_attempts = 0;
            $user->locked_until = null;
        }

        // Case 1: legacy plain-text password stored directly
        if ($user->password_hash === $password) {
            $request->session()->regenerate();
            session(['username' => $user->username]);
            return redirect()->route('changepassword');
        }

        // Case 2: bcrypt-hashed password
        if (Hash::check($password, $user->password_hash)) {
            // Successful login — reset failed attempts
            $user->failed_login_attempts = 0;
            $user->last_login = now();
            $user->save();

            $request->session()->regenerate();
            session([
                'loggedin' => true,
                'user_id' => $user->user_id,
                'username' => $user->username,
                'role' => $user->role,
                'usertype_id' => $user->usertype_id ?? null,
            ]);

            // Force change if password is flagged expired
            if ($user->is_password_expired) {
                session(['username' => $user->username]);
                return redirect()->route('changepassword');
            }

            return redirect()->route('dashboard');
        }

        // Wrong password — increment failed attempts
        $user->failed_login_attempts = ($user->failed_login_attempts ?? 0) + 1;
        $user->last_failed_login = now();

        if ($user->failed_login_attempts >= self::MAX_ATTEMPTS) {
            $user->account_locked = true;
            $user->locked_until = now()->addMinutes(self::LOCKOUT_MINUTES);
            $user->save();
            return back()->withInput()->with('error', 'Too many failed attempts. Account locked for ' . self::LOCKOUT_MINUTES . ' minutes.');
        }

        $user->save();
        return back()->withInput()->with('error', 'Invalid password');
    }

    // POST /logout
    public function logout(Request $request)
    {
        $request->session()->flush();
        $request->session()->regenerate();
        return redirect()->route('login');
    }
}
