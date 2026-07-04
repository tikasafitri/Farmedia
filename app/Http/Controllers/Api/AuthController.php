<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
{
    $data = $request->validate([
        'name' => 'required|string|max:100',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:6|confirmed',
        'role' => 'nullable|in:user,mitra,admin',
        'alamat_lengkap' => 'nullable|string|max:500',
        'mitra_id' => 'nullable|integer|exists:mitras,id',
    ]);

    $user = User::create([
        'name' => $data['name'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
        'role' => $data['role'] ?? 'user',
        'alamat_lengkap' => $data['alamat_lengkap'] ?? null,
        'mitra_id' => $data['mitra_id'] ?? null,
    ]);

    $token = $user->createToken('android')->plainTextToken;

    return response()->json([
        'user' => $user,
        'token' => $token,
    ], 201);
}

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        // opsional: hapus token lama biar 1 device 1 token
        // $user->tokens()->delete();

        $token = $user->createToken('android')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
