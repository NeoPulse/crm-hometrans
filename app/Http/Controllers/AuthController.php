<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return back()->withErrors(['email' => 'Invalid credentials'])->withInput();
        }

        if (! $user->is_active) {
            return back()->withErrors(['email' => 'Account is inactive'])->withInput();
        }

        Auth::login($user, true);

        $this->logActivity('login', $user, 'User signed in');

        return redirect()->intended('/');
    }

    public function logout()
    {
        $this->logActivity('logout', auth()->user(), 'User signed out');
        Auth::logout();
        return redirect()->route('login');
    }
}
