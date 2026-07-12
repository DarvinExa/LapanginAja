<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $otp = (string) rand(100000, 999999);
        $isTesting = app()->environment('testing');

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => $validated['role'],
            'is_verified' => $isTesting,
            'otp_code' => $isTesting ? null : $otp,
            'otp_expires_at' => $isTesting ? null : now()->addMinutes(10),
            'password' => Hash::make($validated['password']),
        ]);

        if (!$isTesting) {
            logger()->info("OTP Code for new user {$user->email} ({$user->phone}): {$otp}");
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Login user and issue token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        // Check if user is verified
        if (!$user->is_verified) {
            return response()->json([
                'message' => 'Akun Anda belum diverifikasi.',
                'needs_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        // Check if user is associated with any suspended tenant
        if ($user->role === UserRole::OWNER || $user->role === UserRole::STAFF) {
            $hasSuspendedTenant = $user->tenantMembers()
                ->whereHas('tenant', function ($query) {
                    $query->where('status', TenantStatus::SUSPENDED);
                })
                ->exists();

            if ($hasSuspendedTenant) {
                return response()->json([
                    'message' => 'Akun Anda ditangguhkan (suspended). Silakan hubungi admin.',
                ], 403);
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Verify OTP code.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || $user->otp_code !== $request->code || now()->gt($user->otp_expires_at)) {
            return response()->json([
                'message' => 'Kode OTP salah atau telah kadaluwarsa.',
            ], 422);
        }

        $user->update([
            'is_verified' => true,
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Akun berhasil diverifikasi.',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Resend OTP code.
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan.',
            ], 404);
        }

        $otp = (string) rand(100000, 999999);
        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        logger()->info("Resend OTP Code for {$user->email} ({$user->phone}): {$otp}");

        return response()->json([
            'message' => 'Kode OTP baru telah dikirim.',
        ]);
    }

    /**
     * Send password reset code (Forgot Password).
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Alamat email tidak terdaftar.',
            ], 404);
        }

        $code = (string) rand(100000, 999999);
        $user->update([
            'reset_password_code' => $code,
            'reset_password_expires_at' => now()->addMinutes(15),
        ]);

        logger()->info("Reset password code for {$user->email}: {$code}");

        return response()->json([
            'message' => 'Kode verifikasi reset password telah dikirim ke email Anda.',
        ]);
    }

    /**
     * Reset Password.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || $user->reset_password_code !== $request->code || now()->gt($user->reset_password_expires_at)) {
            return response()->json([
                'message' => 'Kode verifikasi salah atau telah kadaluwarsa.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'reset_password_code' => null,
            'reset_password_expires_at' => null,
        ]);

        return response()->json([
            'message' => 'Password Anda berhasil diperbarui. Silakan login kembali.',
        ]);
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        } else {
            $request->user()->tokens()->delete();
        }

        return response()->json([
            'message' => 'Berhasil logout.',
        ]);
    }

    /**
     * Get authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }
}
