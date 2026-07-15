import React, { useState } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { useToast } from '../context/ToastContext';

export default function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const { addToast } = useToast();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const from = location.state?.from?.pathname || '/';

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsLoading(true);

    try {
      const loggedUser = await login({ email, password });
      
      if (loggedUser.role === 'owner' || loggedUser.role === 'staff') {
        const tenant = loggedUser.tenants && loggedUser.tenants.length > 0 ? loggedUser.tenants[0] : null;
        if (tenant) {
          navigate(`/admin/${tenant.slug}`, { replace: true });
        } else if (loggedUser.role === 'owner') {
          navigate('/onboard', { replace: true });
        } else {
          navigate('/', { replace: true });
        }
      } else {
        navigate(from, { replace: true });
      }
    } catch (err: any) {
      if (err.response?.data?.needs_verification) {
        addToast('Akun Anda belum diverifikasi. Silakan cek email untuk tautan verifikasi.', 'warning');
        navigate('/verify-email', { state: { email: err.response.data.email } });
        return;
      }
      setError(
        err.response?.data?.message || 'Login gagal. Silakan periksa kembali email dan password Anda.'
      );
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen flex-col justify-center bg-slate-50 py-12 sm:px-6 lg:px-8 px-4">
      <div className="sm:mx-auto sm:w-full sm:max-w-md">
        <h2 className="text-center text-3xl font-extrabold tracking-tight text-slate-900">
          Masuk ke LapanginAja
        </h2>
        <p className="mt-2 text-center text-sm text-slate-600">
          Kelola booking lapangan Anda dengan mudah
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white py-8 px-4 shadow-sm border border-slate-200 sm:rounded-xl sm:px-10">
          <form className="space-y-6" onSubmit={handleSubmit}>
            <Input
              label="Alamat email"
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="Masukkan email Anda"
            />

            <Input
              label="Kata sandi"
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Masukkan kata sandi"
            />

            <div className="-mt-3 text-right">
              <Link
                to="/forgot-password"
                className="text-xs font-semibold text-emerald-600 hover:text-emerald-700 transition-colors"
              >
                Lupa kata sandi?
              </Link>
            </div>

            {error && (
              <div className="rounded-lg bg-rose-50 border border-rose-100 p-3 text-xs font-semibold text-rose-700">
                {error}
              </div>
            )}

            <div>
              <Button
                type="submit"
                className="w-full justify-center"
                isLoading={isLoading}
              >
                Masuk
              </Button>
            </div>
          </form>

          <div className="mt-6 border-t border-slate-100 pt-6 text-center text-xs">
            <span className="text-slate-500">Belum punya akun?</span>{' '}
            <Link
              to="/register"
              className="font-semibold text-emerald-600 hover:text-emerald-700 transition-colors"
            >
              Daftar sekarang
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
