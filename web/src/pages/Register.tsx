import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth, type UserRole } from '../context/AuthContext';
import { Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';

export default function Register() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [role, setRole] = useState<UserRole>('player');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});
    setIsLoading(true);

    if (password !== passwordConfirmation) {
      setErrors({ password_confirmation: 'Konfirmasi kata sandi tidak cocok.' });
      setIsLoading(false);
      return;
    }

    try {
      await register({
        name,
        email,
        phone,
        role,
        password,
        password_confirmation: passwordConfirmation,
      });
      // Redirect to OTP verification page with email
      navigate('/verify-email', { state: { email } });
    } catch (err: any) {
      if (err.response?.data?.errors) {
        // Map Laravel validation errors
        const validationErrors: Record<string, string> = {};
        Object.keys(err.response.data.errors).forEach((key) => {
          validationErrors[key] = err.response.data.errors[key][0];
        });
        setErrors(validationErrors);
      } else {
        setErrors({
          global: err.response?.data?.message || 'Registrasi gagal. Silakan coba lagi.',
        });
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen flex-col justify-center bg-slate-50 py-12 sm:px-6 lg:px-8 px-4">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <h2 className="text-center text-3xl font-extrabold tracking-tight text-slate-900">
          Daftar Akun Baru
        </h2>
        <p className="mt-2 text-center text-sm text-slate-600">
          Mulai memesan dan mengelola lapangan olahraga Anda
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white py-8 px-4 shadow-sm border border-slate-200 sm:rounded-xl sm:px-10">
          <form className="space-y-4" onSubmit={handleSubmit}>
            <Input
              label="Nama lengkap"
              type="text"
              required
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Masukkan nama lengkap Anda"
              error={errors.name}
            />

            <Input
              label="Alamat email"
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="contoh@domain.com"
              error={errors.email}
            />

            <Input
              label="Nomor telepon"
              type="tel"
              required
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              placeholder="0812xxxxxxxx"
              error={errors.phone}
            />

            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-semibold text-slate-800">
                Tipe akun / Peran
              </label>
              <div className="grid grid-cols-2 gap-4">
                <label
                  className={`flex flex-col items-center justify-center p-3 rounded-lg border text-center cursor-pointer transition-all duration-200 ${
                    role === 'player'
                      ? 'border-emerald-600 bg-emerald-50/35 text-emerald-800 font-semibold'
                      : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                  }`}
                >
                  <input
                    type="radio"
                    name="role"
                    value="player"
                    checked={role === 'player'}
                    onChange={() => setRole('player')}
                    className="sr-only"
                  />
                  <span className="text-sm">Pemain</span>
                  <span className="text-xxs text-slate-500 font-normal mt-0.5">
                    Memesan lapangan olahraga
                  </span>
                </label>
                <label
                  className={`flex flex-col items-center justify-center p-3 rounded-lg border text-center cursor-pointer transition-all duration-200 ${
                    role === 'owner'
                      ? 'border-emerald-600 bg-emerald-50/35 text-emerald-800 font-semibold'
                      : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                  }`}
                >
                  <input
                    type="radio"
                    name="role"
                    value="owner"
                    checked={role === 'owner'}
                    onChange={() => setRole('owner')}
                    className="sr-only"
                  />
                  <span className="text-sm">Pemilik Lapangan</span>
                  <span className="text-xxs text-slate-500 font-normal mt-0.5">
                    Mengelola venue & bookings
                  </span>
                </label>
              </div>
            </div>

            <Input
              label="Kata sandi"
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Minimal 8 karakter"
              error={errors.password}
            />

            <Input
              label="Konfirmasi kata sandi"
              type="password"
              required
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              placeholder="Ulangi kata sandi Anda"
              error={errors.password_confirmation}
            />

            {errors.global && (
              <div className="rounded-lg bg-rose-50 border border-rose-100 p-3 text-xs font-semibold text-rose-700">
                {errors.global}
              </div>
            )}

            <div className="pt-2">
              <Button
                type="submit"
                className="w-full justify-center"
                isLoading={isLoading}
              >
                Daftar Akun
              </Button>
            </div>
          </form>

          <div className="mt-6 border-t border-slate-100 pt-6 text-center text-xs">
            <span className="text-slate-500">Sudah memiliki akun?</span>{' '}
            <Link
              to="/login"
              className="font-semibold text-emerald-600 hover:text-emerald-700 transition-colors"
            >
              Masuk disini
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
