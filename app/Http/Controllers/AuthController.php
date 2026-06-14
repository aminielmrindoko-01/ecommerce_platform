<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if ($user && Hash::check($request->password, $user->password)) {

            session([
                'user_id' => $user->id,
                'user_name' => $user->name
            ]);

            return redirect('/')->with('success', 'Logged in!');
        }

        return back()->with('error', 'Invalid credentials');
    }
}
use Illuminate\Support\Facades\Hash;

public function register(Request $request)
{
    // validation
    if ($request->password !== $request->password_confirmation) {
        return back()->with('error', 'Passwords do not match');
    }

    // check if email exists
    if (User::where('email', $request->email)->exists()) {
        return back()->with('error', 'Email already exists');
    }

    // create user
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    // login user automatically
    session([
        'user_id' => $user->id,
        'user_name' => $user->name
    ]);

    return redirect('/')->with('success', 'Account created!');
}