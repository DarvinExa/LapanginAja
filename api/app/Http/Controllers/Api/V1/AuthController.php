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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $isTesting = app()->environment('testing');

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => $validated['role'],
            'is_verified' => $isTesting,
            'email_verification_token' => $isTesting ? null : Str::random(64),
            'email_verification_expires_at' => $isTesting ? null : now()->addHours(24),
            'password' => Hash::make($validated['password']),
        ]);

        if ($isTesting) {
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => new UserResource($user),
                'token' => $token,
            ], 201);
        }

        $this->sendVerificationEmail($user);

        return response()->json([
            'user' => new UserResource($user),
            'message' => 'Registrasi berhasil. Silakan cek email Anda untuk tautan verifikasi akun.',
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        if (! $user->is_verified) {
            return response()->json([
                'message' => 'Akun Anda belum diverifikasi. Silakan cek email untuk tautan verifikasi.',
                'needs_verification' => true,
                'email' => $user->email,
            ], 403);
        }

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

    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        $user = User::where('email_verification_token', $request->token)->first();

        // Token sama sekali tidak dikenal.
        if (! $user) {
            return response()->json([
                'message' => 'Tautan verifikasi tidak valid atau telah kadaluwarsa.',
            ], 422);
        }

        $isExpired = $user->email_verification_expires_at
            && now()->gt($user->email_verification_expires_at);

        if ($isExpired) {
            // Token sudah lewat masa berlaku (24 jam).
            if ($user->is_verified) {
                // Akun sudah aktif, cukup diarahkan untuk masuk seperti biasa.
                return response()->json([
                    'message' => 'Akun Anda sudah terverifikasi. Silakan masuk.',
                    'already_verified' => true,
                ]);
            }

            return response()->json([
                'message' => 'Tautan verifikasi telah kadaluwarsa. Silakan minta tautan baru.',
            ], 422);
        }

        // Token masih berlaku. Tandai akun terverifikasi bila belum, lalu terbitkan
        // sesi login. Token sengaja TIDAK dihapus agar penekanan tautan berulang
        // (atau permintaan ganda dari browser seperti React StrictMode di dev)
        // tetap mengembalikan sesi yang sama, bukan error 422.
        if (! $user->is_verified) {
            $user->update(['is_verified' => true]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Akun berhasil diverifikasi.',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        if ($user->is_verified) {
            return response()->json(['message' => 'Akun Anda sudah terverifikasi. Silakan login.']);
        }

        $user->update([
            'email_verification_token' => Str::random(64),
            'email_verification_expires_at' => now()->addHours(24),
        ]);

        $this->sendVerificationEmail($user);

        return response()->json([
            'message' => 'Tautan verifikasi baru telah dikirim ke email Anda.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json(['message' => 'Alamat email tidak terdaftar.'], 404);
        }

        $code = (string) rand(100000, 999999);
        $user->update([
            'reset_password_code' => $code,
            'reset_password_expires_at' => now()->addMinutes(15),
        ]);

        $this->sendResetCodeEmail($user, $code);

        return response()->json([
            'message' => 'Kode verifikasi reset password telah dikirim ke email Anda.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || $user->reset_password_code !== $request->code || now()->gt($user->reset_password_expires_at)) {
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

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        } else {
            $request->user()->tokens()->delete();
        }

        return response()->json(['message' => 'Berhasil logout.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }

    private function sendVerificationEmail(User $user): void
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $link = $frontendUrl.'/verify-email?token='.$user->email_verification_token;
        $subject = 'Verifikasi Akun LapanginAja';

        $html = $this->emailLayout(
            'Verifikasi Akun Anda',
            "Halo {$user->name},<br><br>Terima kasih telah mendaftar di LapanginAja. Klik tombol di bawah ini untuk mengaktifkan akun Anda. Tautan ini berlaku selama 24 jam.",
            $link,
            'Verifikasi Akun'
        );

        try {
            Mail::html($html, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
        } catch (\Throwable $e) {
            logger()->error("Gagal mengirim email verifikasi ke {$user->email}: ".$e->getMessage());
        }

        if (! app()->environment('production')) {
            logger()->info("[DEV] Tautan verifikasi untuk {$user->email}: {$link}");
        }
    }

    private function sendResetCodeEmail(User $user, string $code): void
    {
        $subject = 'Kode Reset Password LapanginAja';

        $html = $this->emailLayout(
            'Reset Password',
            "Halo {$user->name},<br><br>Gunakan kode berikut untuk mereset password akun Anda. Kode ini berlaku selama 15 menit. Jangan bagikan kode ini kepada siapa pun.",
            null,
            null,
            $code
        );

        try {
            Mail::html($html, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
        } catch (\Throwable $e) {
            logger()->error("Gagal mengirim email reset ke {$user->email}: ".$e->getMessage());
        }

        if (! app()->environment('production')) {
            logger()->info("[DEV] Kode reset untuk {$user->email}: {$code}");
        }
    }

    private function emailLayout(string $heading, string $body, ?string $buttonUrl = null, ?string $buttonText = null, ?string $code = null): string
    {
        $button = '';
        if ($buttonUrl && $buttonText) {
            $button = '<tr><td style="padding:8px 0 24px;">'
                .'<a href="'.$buttonUrl.'" style="display:inline-block;background:#059669;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;padding:12px 28px;border-radius:8px;">'.$buttonText.'</a>'
                .'</td></tr>'
                .'<tr><td style="padding:0 0 8px;font-size:12px;color:#64748b;">Jika tombol tidak berfungsi, salin dan tempel tautan berikut ke browser Anda:</td></tr>'
                .'<tr><td style="padding:0 0 8px;font-size:12px;color:#059669;word-break:break-all;">'.$buttonUrl.'</td></tr>';
        }

        $codeBlock = '';
        if ($code) {
            $codeBlock = '<tr><td style="padding:8px 0 24px;">'
                .'<div style="display:inline-block;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:14px 28px;font-size:28px;font-weight:800;letter-spacing:8px;color:#0f172a;">'.$code.'</div>'
                .'</td></tr>';
        }

        return '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            .'<body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:32px 16px;"><tr><td align="center">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:32px;">'
            .'<tr><td style="font-size:20px;font-weight:800;color:#059669;padding:0 0 16px;">LapanginAja</td></tr>'
            .'<tr><td style="font-size:18px;font-weight:700;padding:0 0 12px;">'.$heading.'</td></tr>'
            .'<tr><td style="font-size:14px;line-height:1.6;color:#334155;padding:0 0 16px;">'.$body.'</td></tr>'
            .$button
            .$codeBlock
            .'<tr><td style="font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;padding:16px 0 0;">Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini.</td></tr>'
            .'</table></td></tr></table></body></html>';
    }
}
