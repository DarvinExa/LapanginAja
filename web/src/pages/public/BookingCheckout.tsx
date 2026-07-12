import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { apiClient } from '../../api/client';
import { Skeleton } from '../../components/ui/Skeleton';
import { Button } from '../../components/ui/Button';
import { Badge } from '../../components/ui/Badge';
import { useToast } from '../../context/ToastContext';
import { Clock, CreditCard } from '@phosphor-icons/react';
import { formatRupiah, formatDateIndo } from '../../utils/format';

declare global {
  interface Window {
    snap: any;
  }
}

interface BookingDetails {
  id: number;
  booking_code: string;
  customer_name: string;
  customer_phone: string;
  customer_email: string;
  start_time: string;
  end_time: string;
  price: number;
  status: string;
  payment_status: string;
  expires_at: string | null;
  court?: {
    name: string;
    sport_type: string;
  } | null;
  payment?: {
    snap_token: string | null;
  } | null;
}

export default function BookingCheckout() {
  const { slug, code } = useParams<{ slug: string; code: string }>();
  const { addToast } = useToast();
  const navigate = useNavigate();

  const [booking, setBooking] = useState<BookingDetails | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [timeLeft, setTimeLeft] = useState<string>('');
  const [isExpired, setIsExpired] = useState(false);
  const [isPolling, setIsPolling] = useState(false);
  const [isSimulating, setIsSimulating] = useState(false);

  const handleSimulatePayment = async () => {
    setIsSimulating(true);
    try {
      await apiClient.post(`/public/${slug}/bookings/${code}/simulate-payment`);
      addToast('Simulasi pembayaran berhasil diproses!', 'success');
      navigate(`/${slug}/bookings/${code}/success`, { replace: true });
    } catch {
      addToast('Gagal memproses simulasi pembayaran.', 'error');
    } finally {
      setIsSimulating(false);
    }
  };

  // 1. Fetch Booking Detail
  const fetchBooking = useCallback(async () => {
    try {
      const response = await apiClient.get(`/public/${slug}/bookings/${code}`);
      setBooking(response.data.booking);
      
      // If already paid, redirect to success page
      if (
        response.data.booking.status === 'confirmed' ||
        response.data.booking.payment_status === 'paid'
      ) {
        navigate(`/${slug}/bookings/${code}/success`, { replace: true });
      }
    } catch {
      addToast('Gagal mengambil data booking.', 'error');
    } finally {
      setIsLoading(false);
    }
  }, [slug, code, navigate, addToast]);

  useEffect(() => {
    fetchBooking();
  }, [fetchBooking]);

  // Load Midtrans Snap script dynamically
  useEffect(() => {
    if (isExpired || !booking) return;
    const scriptId = 'midtrans-snap-script';
    let script = document.getElementById(scriptId) as HTMLScriptElement;
    
    if (!script) {
      script = document.createElement('script');
      script.id = scriptId;
      script.src = 'https://app.sandbox.midtrans.com/snap/snap.js';
      script.setAttribute(
        'data-client-key',
        import.meta.env.VITE_MIDTRANS_CLIENT_KEY || 'SB-Mid-client-key'
      );
      script.type = 'text/javascript';
      script.async = true;
      document.head.appendChild(script);
    }
  }, [booking, isExpired]);

  // 2. Countdown Timer
  useEffect(() => {
    if (!booking || !booking.expires_at || booking.status !== 'pending') return;

    const calculateTimeLeft = () => {
      const difference = +new Date(booking.expires_at!) - +new Date();
      if (difference <= 0) {
        setTimeLeft('00:00');
        setIsExpired(true);
        return;
      }

      const minutes = Math.floor((difference / 1000 / 60) % 60);
      const seconds = Math.floor((difference / 1000) % 60);

      const displayMin = String(minutes).padStart(2, '0');
      const displaySec = String(seconds).padStart(2, '0');

      setTimeLeft(`${displayMin}:${displaySec}`);
    };

    calculateTimeLeft();
    const timer = setInterval(calculateTimeLeft, 1000);

    return () => clearInterval(timer);
  }, [booking]);

  // 3. Polling Payment Status
  const startPolling = useCallback(() => {
    if (isPolling) return;
    setIsPolling(true);
    let pollAttempts = 0;

    const interval = setInterval(async () => {
      pollAttempts++;
      try {
        const response = await apiClient.get(
          `/public/${slug}/bookings/${code}/payment-status`
        );
        const { status, payment_status } = response.data;

        if (status === 'confirmed' || payment_status === 'paid') {
          clearInterval(interval);
          addToast('Pembayaran berhasil dikonfirmasi!', 'success');
          navigate(`/${slug}/bookings/${code}/success`, { replace: true });
        } else if (status === 'cancelled' || payment_status === 'failed') {
          clearInterval(interval);
          addToast('Pembayaran gagal atau kedaluwarsa.', 'error');
          setIsExpired(true);
        }
      } catch {
        // Ignore check errors, continue polling
      }

      // Stop polling after 10 minutes (200 attempts × 3s)
      if (pollAttempts >= 200) {
        clearInterval(interval);
        setIsPolling(false);
      }
    }, 3000);

    return () => clearInterval(interval);
  }, [slug, code, navigate, isPolling, addToast]);

  // 4. Trigger Midtrans Snap
  const handlePay = () => {
    if (isExpired) {
      addToast('Waktu pembayaran telah habis.', 'error');
      return;
    }

    const snapToken = booking?.payment?.snap_token;
    if (!snapToken) {
      addToast('Token pembayaran tidak ditemukan. Silakan hubungi admin.', 'error');
      return;
    }

    if (window.snap) {
      window.snap.pay(snapToken, {
        onSuccess: () => {
          addToast('Pembayaran berhasil diproses!', 'success');
          startPolling();
        },
        onPending: () => {
          addToast('Menunggu penyelesaian pembayaran Anda.', 'info');
          startPolling();
        },
        onError: () => {
          addToast('Pembayaran gagal dilakukan. Silakan coba lagi.', 'error');
        },
        onClose: () => {
          addToast('Pendaftaran pembayaran ditutup.', 'info');
        },
      });
    } else {
      addToast('Midtrans SDK gagal dimuat. Coba muat ulang halaman.', 'error');
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-slate-50 py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-md mx-auto flex flex-col gap-6">
          <Skeleton className="h-40 w-full rounded-xl" />
          <Skeleton className="h-60 w-full rounded-xl" />
        </div>
      </div>
    );
  }

  if (!booking) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
        <div className="bg-white border border-slate-200 p-8 rounded-xl max-w-md w-full text-center shadow-sm">
          <h3 className="text-rose-500 font-bold text-lg mb-2">Booking Tidak Ditemukan</h3>
          <p className="text-slate-600 text-sm mb-6">
            Kode booking yang Anda cari salah atau tidak terdaftar.
          </p>
          <Button onClick={() => navigate('/')} className="w-full justify-center">
            Kembali ke Beranda
          </Button>
        </div>
      </div>
    );
  }

  // Local formatters removed (using shared utils)

  return (
    <div className="min-h-screen bg-slate-50 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md mx-auto flex flex-col gap-6">
        
        {/* Countdown Header */}
        {booking.status === 'pending' && !isExpired && (
          <div className="bg-amber-50 border border-amber-200 rounded-xl p-5 shadow-sm text-center flex flex-col items-center gap-1 animate-pulse">
            <div className="flex items-center gap-1.5 text-amber-800 font-semibold text-xs uppercase tracking-wider">
              <Clock size={16} />
              Selesaikan Pembayaran Dalam
            </div>
            <div className="text-3xl font-extrabold text-amber-900 mt-1">
              {timeLeft}
            </div>
          </div>
        )}

        {isExpired && (
          <div className="bg-rose-50 border border-rose-200 rounded-xl p-5 shadow-sm text-center flex flex-col items-center gap-1">
            <div className="text-rose-800 font-bold text-sm">Waktu Pembayaran Habis</div>
            <p className="text-xs text-rose-600">
              Sesi pembayaran Anda telah kedaluwarsa. Slot waktu ini telah dilepas otomatis. Silakan lakukan pemesanan ulang.
            </p>
          </div>
        )}

        {/* Booking Details Card */}
        <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
          <div className="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <div>
              <span className="text-xxs uppercase tracking-wider font-semibold text-slate-400">Kode Booking</span>
              <h2 className="text-base font-extrabold text-slate-800 mt-0.5">{booking.booking_code}</h2>
            </div>
            <Badge variant={isExpired ? 'error' : 'warning'}>
              {isExpired ? 'Kedaluwarsa' : 'Menunggu Bayar'}
            </Badge>
          </div>

          <div className="p-5 flex flex-col gap-4 text-sm">
            <div className="flex justify-between items-center pb-3 border-b border-slate-100">
              <span className="text-slate-500">Pemain / Pemesan</span>
              <span className="font-semibold text-slate-800">{booking.customer_name}</span>
            </div>

            <div className="flex justify-between items-center pb-3 border-b border-slate-100">
              <span className="text-slate-500">Lapangan</span>
              <span className="font-semibold text-slate-800">
                {booking.court?.name ?? 'Lapangan'} ({booking.court?.sport_type ?? 'Umum'})
              </span>
            </div>

            <div className="flex justify-between items-center pb-3 border-b border-slate-100">
              <span className="text-slate-500">Jadwal Main</span>
              <span className="font-semibold text-slate-800 text-right">
                {formatDateIndo(booking.start_time)}
                <br />
                <span className="text-xs text-slate-400 font-normal">
                  {booking.start_time.split(' ')[1]?.substring(0, 5)} - {booking.end_time.split(' ')[1]?.substring(0, 5)}
                </span>
              </span>
            </div>

            <div className="flex justify-between items-center pt-2">
              <span className="text-base font-bold text-slate-800">Total Pembayaran</span>
              <span className="text-xl font-black text-emerald-600">
                {formatRupiah(booking.price)}
              </span>
            </div>
          </div>
        </div>

        {/* Action Button */}
        {!isExpired && (
          <div className="flex flex-col gap-3">
            {booking?.payment?.snap_token?.startsWith('mock-') ? (
              <Button
                onClick={handleSimulatePayment}
                className="w-full py-3.5 justify-center text-base"
                isLoading={isSimulating}
              >
                Bayar Instan (Demo Mode)
              </Button>
            ) : (
              <Button
                onClick={handlePay}
                className="w-full py-3.5 justify-center text-base"
                icon={<CreditCard size={20} />}
                isLoading={isPolling}
              >
                {isPolling ? 'Memverifikasi Pembayaran...' : 'Bayar Sekarang'}
              </Button>
            )}
          </div>
        )}

        {isExpired && (
          <Button
            onClick={() => navigate(`/${slug}`)}
            className="w-full py-3.5 justify-center text-base"
            variant="secondary"
          >
            Pesan Lapangan Lagi
          </Button>
        )}
      </div>
    </div>
  );
}
