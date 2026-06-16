<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;


class AuthController extends Controller
{

    // Show login page
    public function showLogin()
    {
        return view('login');
    }


    // Login user
    public function login(Request $request)
    {

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);


        if(Auth::attempt($credentials)){

            $request->session()->regenerate();

            return redirect('/products');

        }


        return back()->withErrors([
            'email' => 'Invalid login details'
        ]);

    }



    // Show register page
    public function showRegister()
    {
        return view('register');
    }



    // Register user
    public function register(Request $request)
    {

        $request->validate([

            'name' => 'required',

            'email' => 'required|email|unique:users',

            'password' => 'required|min:6'

        ]);


        User::create([

            'name' => $request->name,

            'email' => $request->email,

            'password' => Hash::make($request->password)

        ]);


        return redirect('/login')
            ->with('success','Account created successfully. Please login.');

    }



    // Logout user
    public function logout(Request $request)
    {

        Auth::logout();


        $request->session()->invalidate();


        $request->session()->regenerateToken();


        return redirect('/login');

    }

}