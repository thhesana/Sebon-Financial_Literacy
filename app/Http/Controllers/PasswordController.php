<?php

namespace App\Http\Controllers;

use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    // GET /changepassword
    public function showForm()
    {
        if (!session('username')) {
            return redirect()->route('login');
        }
        return view('auth.changepassword');
    }

    // POST /changepassword
    public function update(Request $request)
    {
        $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:8',
            'confirm_password' => 'required|string',
        ]);

        $username = session('username');
        if (!$username) {
            return redirect()->route('login');
        }

        $user = UserDetail::where('username', $username)
            ->where('active', 'Y')
            ->first();

        if (!$user) {
            return back()->with('error', 'User not found or account not active!');
        }

        $old = $request->input('old_password');

        // Old password may still be plain text (first-time change) or already hashed
        $oldMatches = ($user->password_hash === $old) || Hash::check($old, $user->password_hash);

        if (!$oldMatches) {
            return back()->with('error', 'Old password is incorrect!');
        }

        if ($request->input('new_password') !== $request->input('confirm_password')) {
            return back()->with('error', 'New password and confirmation do not match!');
        }

        $user->password_hash = Hash::make($request->input('new_password'));
        $user->password_changed_at = now();
        $user->is_password_expired = false;
        $user->save();

        session()->forget('username');

        return redirect()->route('login')->with('success', 'Password updated successfully! Please log in.');
    }
}