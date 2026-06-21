<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Đăng ký - mặc định gán role CUSTOMER
     * Mật khẩu BẮT BUỘC mã hóa bằng Hash::make() (bcrypt) trước khi lưu DB.
     */
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
            'password_hash' => Hash::make($data['password']), // mã hóa mật khẩu - yêu cầu 1.2
            'role_id'       => $customerRole->id,
        ]);

        Auth::login($user); // tạo session, Laravel tự set cookie session (httpOnly)
        $request->session()->regenerate(); // chống session fixation attack

        return response()->json(['user' => $this->formatUser($user)]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không đúng'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Tài khoản đã bị khóa'],
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $this->formatUser($user)]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

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
