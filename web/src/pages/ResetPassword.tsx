import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { apiClient } from '../api/client';
import { Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { useToast } from '../context/ToastContext';
import { LockOpen } from '@phosphor-icons/react';

export default function ResetPassword() {
  const navigate = useNavigate();
  const location = useLocation();
  const { addToast } = useToast();

  const [email, setEmail] = useState('');
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const passedEmail = location.state?.email;
    if (passedEmail) {
      setEmail(passedEmail);
    }
  }, [location]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsLoading(true);

    if (password !== passwordConfirmation) {
      setError('Konfirmasi kata sandi tidak cocok.');
      setIsLoading(false);
      return;
    }

    try {
      await apiClient.post('/auth/reset-password', {
        email,
        code,
        password,
        password_confirmation: passwordConfirmation,
      });

      addToast('Kata sandi Anda berhasil diubah! Silakan masuk kembali.', 'success');
      navigate('/login', { replace: true });
    } catch (err: any) {
      setError(
        err.response?.data?.message || 'Gagal mengubah kata sandi. Pastikan kode verifikasi benar.'
      );
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen flex-col justify-center bg-[#FDFBF7] py-12 sm:px-6 lg:px-8 px-4">
      <div className="sm:mx-auto sm:w-full sm:max-w-md text-center flex flex-col items-center gap-2">
        <LockOpen size={48} className="text-[#10B981] animate-bounce" weight="fill" />
        <h2 className="text-center text-3xl font-extrabold tracking-tight text-[#064E3B]">
          Ubah Kata Sandi
        </h2>
        <p className="mt-2 text-center text-sm text-[#064E3B]/80">
          Masukkan kode verifikasi yang Anda terima beserta kata sandi baru Anda.
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-[#FDFBF7] py-8 px-4 shadow-[4px_4px_0_#064E3B] border border-[#064E3B] sm:rounded-none sm:px-10">
          <form className="space-y-4" onSubmit={handleSubmit}>
            <Input
              label="Alamat Email"
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="contoh@domain.com"
            />

            <Input
              label="Kode Verifikasi"
              type="text"
              required
              maxLength={6}
              value={code}
              onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
              placeholder="Masukkan 6 digit kode"
              className="text-center tracking-widest text-base font-bold"
            />

            <Input
              label="Kata Sandi Baru"
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Minimal 8 karakter"
            />

            <Input
              label="Konfirmasi Kata Sandi Baru"
              type="password"
              required
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              placeholder="Masukkan kembali kata sandi"
            />

            {error && (
              <div className="rounded-none bg-rose-50 border border-rose-100 p-3 text-xs font-semibold text-rose-700">
                {error}
              </div>
            )}

            <Button
              type="submit"
              className="w-full justify-center py-3 text-sm font-semibold"
              isLoading={isLoading}
            >
              Ubah Kata Sandi
            </Button>
          </form>

          <div className="mt-6 border-t border-[#064E3B] pt-6 text-center text-xs">
            <Link
              to="/login"
              className="font-semibold text-[#10B981] hover:text-[#064E3B] transition-colors"
            >
              Batal dan Kembali Masuk
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
