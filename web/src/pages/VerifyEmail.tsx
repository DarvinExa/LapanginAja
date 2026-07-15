import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate, useLocation, useSearchParams, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { apiClient } from '../api/client';
import { Button } from '../components/ui/Button';
import { useToast } from '../context/ToastContext';
import { EnvelopeSimple, CheckCircle, XCircle } from '@phosphor-icons/react';

type Status = 'sent' | 'verifying' | 'success' | 'error';

export default function VerifyEmail() {
  const { verifyLogin } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [searchParams] = useSearchParams();
  const { addToast } = useToast();

  const token = searchParams.get('token');
  const email: string | undefined = location.state?.email;

  const [status, setStatus] = useState<Status>(token ? 'verifying' : 'sent');
  const [error, setError] = useState<string | null>(null);
  const [isResending, setIsResending] = useState(false);

  const verify = useCallback(async () => {
    if (!token) return;
    setStatus('verifying');
    setError(null);

    try {
      const response = await apiClient.post('/auth/verify-email', { token });

      // Akun sudah terverifikasi sebelumnya dan tautannya sudah lewat masa berlaku:
      // tidak ada sesi baru yang diterbitkan, jadi arahkan pengguna untuk masuk biasa.
      if (response.data.already_verified) {
        addToast(response.data.message || 'Akun Anda sudah terverifikasi. Silakan masuk.', 'success');
        navigate('/login', { replace: true });
        return;
      }

      const { token: authToken, user } = response.data;
      verifyLogin(authToken, user);
      setStatus('success');
      addToast('Akun Anda berhasil diverifikasi.', 'success');

      setTimeout(() => {
        if (user.role === 'owner') {
          navigate('/onboard', { replace: true });
        } else {
          navigate('/', { replace: true });
        }
      }, 1200);
    } catch (err: any) {
      setStatus('error');
      setError(err.response?.data?.message || 'Tautan verifikasi tidak valid atau telah kadaluwarsa.');
    }
  }, [token, verifyLogin, addToast, navigate]);

  const hasRunVerification = useRef(false);

  useEffect(() => {
    // Cegah verifikasi berjalan lebih dari sekali. React StrictMode (dev)
    // menjalankan effect dua kali dan perubahan state auth bisa memicu render
    // ulang; tanpa penjaga ini beberapa permintaan verify-email terkirim
    // bersamaan sehingga sebagian gagal 422 dan sempat muncul "Verifikasi Gagal".
    if (!token || hasRunVerification.current) return;
    hasRunVerification.current = true;
    verify();
  }, [token, verify]);

  const handleResend = async () => {
    if (!email) {
      addToast('Email tidak ditemukan. Silakan daftar atau masuk kembali.', 'error');
      navigate('/login');
      return;
    }
    setIsResending(true);
    try {
      await apiClient.post('/auth/resend-verification', { email });
      addToast('Tautan verifikasi baru telah dikirim ke email Anda.', 'success');
    } catch (err: any) {
      addToast(err.response?.data?.message || 'Gagal mengirim ulang tautan verifikasi.', 'error');
    } finally {
      setIsResending(false);
    }
  };

  return (
    <div className="flex min-h-screen flex-col justify-center bg-slate-50 py-12 sm:px-6 lg:px-8 px-4">
      <div className="sm:mx-auto sm:w-full sm:max-w-md text-center flex flex-col items-center gap-2">
        {status === 'success' ? (
          <CheckCircle size={48} className="text-emerald-600" weight="fill" />
        ) : status === 'error' ? (
          <XCircle size={48} className="text-rose-500" weight="fill" />
        ) : (
          <EnvelopeSimple size={48} className="text-emerald-600" weight="fill" />
        )}
        <h2 className="text-center text-3xl font-extrabold tracking-tight text-slate-900">
          {status === 'success'
            ? 'Akun Terverifikasi'
            : status === 'error'
            ? 'Verifikasi Gagal'
            : status === 'verifying'
            ? 'Memverifikasi Akun'
            : 'Cek Email Anda'}
        </h2>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white py-8 px-4 shadow-sm border border-slate-200 sm:rounded-xl sm:px-10">
          {status === 'sent' && (
            <div className="flex flex-col gap-4 text-center">
              <p className="text-sm text-slate-600 leading-relaxed">
                Kami telah mengirim tautan verifikasi ke email Anda
                {email ? ': ' : '. '}
                {email && <span className="font-semibold text-slate-800">{email}</span>}
                {email && '. '}
                Klik tautan di email tersebut untuk mengaktifkan akun Anda.
              </p>
              <div className="border-t border-slate-100 pt-4 text-xs flex flex-col gap-2">
                <span className="text-slate-500">Belum menerima email?</span>
                <button
                  onClick={handleResend}
                  disabled={isResending}
                  className="font-bold text-emerald-600 hover:text-emerald-700 disabled:text-slate-400 transition-colors focus:outline-none cursor-pointer"
                >
                  {isResending ? 'Mengirim ulang...' : 'Kirim Ulang Tautan Verifikasi'}
                </button>
              </div>
            </div>
          )}

          {status === 'verifying' && (
            <p className="text-sm text-slate-600 text-center">Mohon tunggu, akun Anda sedang diverifikasi...</p>
          )}

          {status === 'success' && (
            <p className="text-sm text-slate-600 text-center">Verifikasi berhasil. Anda akan diarahkan secara otomatis...</p>
          )}

          {status === 'error' && (
            <div className="flex flex-col gap-4 text-center">
              <div className="rounded-lg bg-rose-50 border border-rose-100 p-3 text-xs font-semibold text-rose-700">{error}</div>
              <Button onClick={handleResend} isLoading={isResending} className="w-full justify-center py-3 text-sm font-semibold">
                Kirim Ulang Tautan Verifikasi
              </Button>
              <Link to="/login" className="text-xs font-semibold text-emerald-600 hover:text-emerald-700 transition-colors">
                Kembali ke halaman masuk
              </Link>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
