import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { apiClient } from '../api/client';
import { Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { useToast } from '../context/ToastContext';
import { ShieldCheck } from '@phosphor-icons/react';

export default function VerifyOtp() {
  const { verifyLogin } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const { addToast } = useToast();

  const [email, setEmail] = useState('');
  const [code, setCode] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [isResending, setIsResending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const passedEmail = location.state?.email;
    if (passedEmail) {
      setEmail(passedEmail);
    } else {
      addToast('Email tidak ditemukan, silakan masuk/daftar kembali.', 'error');
      navigate('/login');
    }
  }, [location, navigate, addToast]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsLoading(true);

    try {
      const response = await apiClient.post('/auth/verify-otp', {
        email,
        code,
      });

      const { token, user } = response.data;
      verifyLogin(token, user);

      addToast('Akun Anda berhasil diverifikasi!', 'success');

      // Redirect to onboard if owner, otherwise home
      if (user.role === 'owner') {
        navigate('/onboard', { replace: true });
      } else {
        navigate('/', { replace: true });
      }
    } catch (err: any) {
      setError(
        err.response?.data?.message || 'Verifikasi gagal. Pastikan kode OTP yang Anda masukkan benar.'
      );
    } finally {
      setIsLoading(false);
    }
  };

  const handleResendOtp = async () => {
    setError(null);
    setIsResending(true);

    try {
      await apiClient.post('/auth/resend-otp', { email });
      addToast('Kode OTP baru telah dikirim ke WhatsApp/Email Anda.', 'success');
    } catch (err: any) {
      addToast(err.response?.data?.message || 'Gagal mengirim ulang OTP.', 'error');
    } finally {
      setIsResending(false);
    }
  };

  return (
    <div className="flex min-h-screen flex-col justify-center bg-slate-50 py-12 sm:px-6 lg:px-8 px-4">
      <div className="sm:mx-auto sm:w-full sm:max-w-md text-center flex flex-col items-center gap-2">
        <ShieldCheck size={48} className="text-emerald-600 animate-pulse" weight="fill" />
        <h2 className="text-center text-3xl font-extrabold tracking-tight text-slate-900">
          Verifikasi Akun
        </h2>
        <p className="mt-2 text-center text-sm text-slate-600">
          Masukkan 6-digit kode OTP yang telah dikirim ke <span className="font-semibold text-slate-800">{email}</span>
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white py-8 px-4 shadow-sm border border-slate-200 sm:rounded-xl sm:px-10">
          <form className="space-y-6" onSubmit={handleSubmit}>
            <Input
              label="Kode OTP"
              type="text"
              required
              maxLength={6}
              value={code}
              onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
              placeholder="Masukkan 6 digit angka"
              className="text-center tracking-widest text-lg font-bold"
            />

            {error && (
              <div className="rounded-lg bg-rose-50 border border-rose-100 p-3 text-xs font-semibold text-rose-700">
                {error}
              </div>
            )}

            <Button
              type="submit"
              className="w-full justify-center py-3 text-sm font-semibold"
              isLoading={isLoading}
            >
              Verifikasi Sekarang
            </Button>
          </form>

          <div className="mt-6 border-t border-slate-100 pt-6 text-center text-xs flex flex-col gap-2">
            <span className="text-slate-500">Belum menerima kode OTP?</span>
            <button
              onClick={handleResendOtp}
              disabled={isResending}
              className="font-bold text-emerald-600 hover:text-emerald-700 disabled:text-slate-400 transition-colors focus:outline-none cursor-pointer"
            >
              {isResending ? 'Mengirim ulang...' : 'Kirim Ulang Kode OTP'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
