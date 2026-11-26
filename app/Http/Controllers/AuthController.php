<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** @group Accounts */
class AuthController extends Controller
{
    /**
     * Create an account
     * @bodyParam name string required Display name. Example: Sara
     * @bodyParam email string required Email address. Example: sara@example.test
     * @bodyParam password string required Password (8 to 72 characters). Example: ExamplePassword123
     * @unauthenticated
     * @response 201 {"message":"Account created."}
     * @response 422 {"message":"The email has already been taken.","errors":{"email":["The email has already been taken."]}}
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8), 'max:72'],
        ]);
        User::create($data);
        return response()->json(['message' => 'Account created.'], 201);
    }

    /**
     * Sign in and issue a bearer token
     * @bodyParam email string required Registered email. Example: sara@example.test
     * @bodyParam password string required Account password. Example: ExamplePassword123
     * @unauthenticated
     * @response 200 {"token":"example-token"}
     * @response 401 {"message":"Invalid credentials."}
     */
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', $data['email'])->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 401, 'Invalid credentials.');
        return response()->json(['token' => $user->createToken('reports')->plainTextToken]);
    }

    /**
     * Revoke the current token
     * @response 204
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->noContent();
    }
}
