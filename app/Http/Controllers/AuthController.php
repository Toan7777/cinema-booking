<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'fullName' => 'required|string|min:2',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $customerRole = Role::firstOrCreate(['name' => 'CUSTOMER']);

        $user = User::create([
            'full_name'     => $data['fullName'],
            'email'         => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'role_id'       => $customerRole->id,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['user' => $this->formatUser($user), 'token' => $token]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password_hash)) {
            throw ValidationException::withMessages(['email' => ['Email hoặc mật khẩu không đúng']]);
        }
        if (!$user->is_active) {
            throw ValidationException::withMessages(['email' => ['Tài khoản đã bị khóa']]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['user' => $this->formatUser($user), 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Đăng xuất thành công']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->formatUser($request->user())]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'       => $user->id,
            'fullName' => $user->full_name,
            'email'    => $user->email,
            'role'     => $user->role->name,
        ];
    }
}
