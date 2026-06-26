<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    /**
     * Step 1: Request reset — kirim email + info WA
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', strtolower($request->email))->first();

        $waNumber = env('WA_CS_NUMBER', '6281296998827');

        if (!$user) {
            // Email tidak ditemukan — tetap return WA sebagai fallback
            $waMessage = urlencode("Halo Mikala, saya ingin reset password akun saya dengan email: {$request->email}");
            return response()->json([
                'success'    => true,
                'message'    => 'Jika email terdaftar, instruksi reset akan dikirim.',
                'email_sent' => false,
                'wa_url'     => "https://wa.me/{$waNumber}?text={$waMessage}",
                'wa_number'  => $waNumber,
            ]);
        }

        // Generate token
        $token = Str::random(64);

        // Simpan ke DB (upsert)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        // Deteksi role -> tentukan URL frontend yang sesuai
        $frontendUrl = match ($user->role) {
            'klien'  => env('FRONTEND_KLIEN_URL', 'https://mikala-web-klien.vercel.app'),
            'mitra'  => env('FRONTEND_MITRA_URL', 'https://mikala-web-mitra.vercel.app'),
            default  => env('FRONTEND_MITRA_URL', 'https://mikala-web-mitra.vercel.app'),
        };
        $resetUrl = $frontendUrl
            . '/auth/reset-password?token=' . $token . '&email=' . urlencode($user->email);

        $mailError = null;
        // Kirim email
        try {
            Mail::send([], [], function ($message) use ($user, $resetUrl) {
                $message->to($user->email, $user->name)
                    ->subject('Reset Password - Mikala Global Medika')
                    ->html("
                        <div style='font-family:sans-serif;max-width:500px;margin:auto;padding:24px'>
                            <img src='https://res.cloudinary.com/djgtchmsx/image/upload/v1779019648/logo_MGM_remake_-_w_font_xtgtt0.png' height='40' style='margin-bottom:20px'>
                            <h2 style='color:#1a1a2e'>Reset Password Akun</h2>
                            <p>Halo <strong>{$user->name}</strong>,</p>
                            <p>Kami menerima permintaan reset password untuk akun Anda. Klik tombol di bawah untuk membuat password baru:</p>
                            <a href='{$resetUrl}' style='display:inline-block;margin:20px 0;padding:14px 28px;background:linear-gradient(135deg,#7c3aed,#4f46e5);color:white;text-decoration:none;border-radius:12px;font-weight:700'>
                                Reset Password
                            </a>
                            <p style='color:#666;font-size:13px'>Link ini berlaku selama <strong>60 menit</strong>. Abaikan email ini jika Anda tidak meminta reset password.</p>
                            <p style='color:#666;font-size:13px'>Atau copy link ini:<br><small>{$resetUrl}</small></p>
                            <hr style='margin:20px 0;border:none;border-top:1px solid #eee'>
                            <p style='color:#999;font-size:12px'>Mikala Global Medika — Layanan Homecare Profesional</p>
                        </div>
                    ");
            });
            $emailSent = true;
        } catch (\Throwable $e) {
            \Log::error('MAIL SEND FAILED: ' . $e->getMessage());
            $emailSent = false;
            $mailError = $e->getMessage();
        }

        // WA link (untuk fallback / alternatif)
        $waNumber = env('WA_CS_NUMBER', '6281296998827');
        $waMessage = urlencode("Halo Mikala, saya {$user->name} ({$user->email}) ingin reset password akun saya.");
        $waUrl = "https://wa.me/{$waNumber}?text={$waMessage}";

        return response()->json([
            'success'    => true,
            'message'    => 'Instruksi reset password telah dikirim.',
            'email_sent' => $emailSent,
            'wa_url'     => $waUrl,
            'wa_number'  => $waNumber,
            // Hanya untuk development/mailtrap — hapus di production
            'reset_url_dev' => app()->environment('local') ? $resetUrl : null,
        ]);
    }

    /**
     * Step 2: Validasi token
     */
    public function validateToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', strtolower($request->email))
            ->first();

        if (!$record) {
            return response()->json(['valid' => false, 'message' => 'Token tidak valid'], 400);
        }

        // Cek expired (60 menit)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['valid' => false, 'message' => 'Token sudah kadaluarsa'], 400);
        }

        if (!Hash::check($request->token, $record->token)) {
            return response()->json(['valid' => false, 'message' => 'Token tidak valid'], 400);
        }

        return response()->json(['valid' => true, 'message' => 'Token valid']);
    }

    /**
     * Step 3: Reset password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email',
            'token'                 => 'required|string',
            'password'              => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', strtolower($request->email))
            ->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json(['success' => false, 'message' => 'Token tidak valid atau sudah digunakan'], 400);
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['success' => false, 'message' => 'Token sudah kadaluarsa, silakan request ulang'], 400);
        }

        $user = User::where('email', strtolower($request->email))->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        // Update password
        $user->update(['password' => Hash::make($request->password)]);

        // Hapus token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Revoke semua token aktif (logout dari semua device)
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah! Silakan login dengan password baru.',
        ]);
    }
}
