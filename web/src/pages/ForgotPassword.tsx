import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { apiClient } from '../api/client';
import { Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { useToast } from '../context/ToastContext';
import { Key } from '@phosphor-icons/react';

export default function ForgotPassword() {
  const navigate = useNavigate();
  const { addToast } = useToast();

  const [email, setEmail] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsLoading(true);

    try {
      await apiClient.post('/auth/forgot-password', { email });
      addToast('Kode verifikasi reset password berhasil dikirim ke email Anda!', 'success');
      // Redirect to ResetPassword page with email state
      navigate('/reset-password', { state: { email } });
    } catch (err: any) {
      setError(
        err.response?.data?.message || 'Email tidak terdaftar atau gagal mengirim kode verifikasi.'
      );
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen flex-col justify-center bg-slate-50 py-12 sm:px-6 lg:px-8 px-4">
      <div className="sm:mx-auto sm:w-full sm:max-w-md text-center flex flex-col items-center gap-2">
        <Key size={48} className="text-emerald-600" weight="fill" />
        <h2 className="text-center text-3xl font-extrabold tracking-tight text-slate-900">
          Lupa Kata Sandi?
        </h2>
        <p className="mt-2 text-center text-sm text-slate-600">
          Masukkan alamat email Anda untuk menerima kode OTP pemulihan kata sandi.
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white py-8 px-4 shadow-sm border border-slate-200 sm:rounded-xl sm:px-10">
          <form className="space-y-6" onSubmit={handleSubmit}>
            <Input
              label="Alamat Email"
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="contoh@domain.com"
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
              Kirim Kode Verifikasi
            </Button>
          </form>

          <div className="mt-6 border-t border-slate-100 pt-6 text-center text-xs">
            <Link
              to="/login"
              className="font-semibold text-emerald-600 hover:text-emerald-700 transition-colors"
            >
              Kembali ke Halaman Masuk
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
