import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { apiClient } from '../../api/client';
import { Skeleton } from '../../components/ui/Skeleton';
import { Button } from '../../components/ui/Button';
import { CheckCircle, Calendar, Clock, User, Phone } from '@phosphor-icons/react';
import { formatRupiah, formatDateIndoLong } from '../../utils/format';

interface BookingDetails {
  booking_code: string;
  customer_name: string;
  customer_phone: string;
  customer_email: string;
  start_time: string;
  end_time: string;
  price: number;
  court?: {
    name: string;
    sport_type: string;
  } | null;
}

export default function BookingSuccess() {
  const { slug, code } = useParams<{ slug: string; code: string }>();
  const navigate = useNavigate();

  const [booking, setBooking] = useState<BookingDetails | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const fetchBooking = useCallback(async () => {
    try {
      const response = await apiClient.get(`/public/${slug}/bookings/${code}`);
      setBooking(response.data.booking);
    } catch {
      // Ignore / handled
    } finally {
      setIsLoading(false);
    }
  }, [slug, code]);

  useEffect(() => {
    fetchBooking();
  }, [fetchBooking]);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-slate-50 py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-md mx-auto flex flex-col gap-6">
          <Skeleton className="h-60 w-full rounded-xl animate-pulse" />
        </div>
      </div>
    );
  }

  if (!booking) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
        <div className="bg-white border border-slate-200 p-8 rounded-xl max-w-md w-full text-center shadow-sm">
          <h3 className="text-rose-500 font-bold text-lg mb-2">Booking Tidak Ditemukan</h3>
          <Button onClick={() => navigate('/')} className="w-full justify-center mt-4">
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
        
        {/* Success Header Card */}
        <div className="bg-white border border-slate-200 rounded-xl p-8 shadow-sm text-center flex flex-col items-center gap-4">
          <CheckCircle size={64} className="text-emerald-500" weight="fill" />
          <div>
            <h2 className="text-2xl font-black text-slate-800">Booking Berhasil!</h2>
            <p className="text-slate-500 text-xs mt-1">
              Pembayaran Anda telah sukses diverifikasi secara otomatis.
            </p>
          </div>
          
          <div className="w-full bg-slate-50 border border-slate-100 rounded-lg py-3 px-4 mt-2">
            <span className="text-xxs uppercase tracking-wider font-semibold text-slate-400">Kode Booking Anda</span>
            <div className="text-2xl font-black text-emerald-700 tracking-wider mt-0.5 select-all">
              {booking.booking_code}
            </div>
          </div>
          
          <p className="text-xs text-slate-400 leading-relaxed px-2">
            E-ticket masuk, QR code presensi, dan invoice PDF resmi telah berhasil dikirimkan ke alamat email Anda.
          </p>
        </div>

        {/* Detail Booking Info */}
        <div className="bg-white border border-slate-200 rounded-xl p-6 shadow-sm flex flex-col gap-4 text-sm">
          <h3 className="font-bold text-slate-800 border-b border-slate-100 pb-2">Detail Pemesanan</h3>

          <div className="flex gap-3">
            <User size={18} className="text-slate-400 shrink-0 mt-0.5" />
            <div className="flex flex-col">
              <span className="text-xxs uppercase tracking-wider font-semibold text-slate-400">Pemain</span>
              <span className="font-semibold text-slate-700">{booking.customer_name}</span>
            </div>
          </div>

          <div className="flex gap-3">
            <Calendar size={18} className="text-slate-400 shrink-0 mt-0.5" />
            <div className="flex flex-col">
              <span className="text-xxs uppercase tracking-wider font-semibold text-slate-400">Jadwal Main</span>
              <span className="font-semibold text-slate-700">
                {formatDateIndoLong(booking.start_time)}
                <br />
                <span className="text-xs text-slate-500 font-normal">
                  {booking.court?.name ?? 'Lapangan'} ({booking.court?.sport_type ?? 'Umum'})
                </span>
              </span>
            </div>
          </div>

          <div className="flex gap-3">
            <Clock size={18} className="text-slate-400 shrink-0 mt-0.5" />
            <div className="flex flex-col">
              <span className="text-xxs uppercase tracking-wider font-semibold text-slate-400">Jam Booking</span>
              <span className="font-semibold text-slate-700">
                {booking.start_time.split(' ')[1]?.substring(0, 5)} - {booking.end_time.split(' ')[1]?.substring(0, 5)}
              </span>
            </div>
          </div>

          <div className="flex gap-3">
            <Phone size={18} className="text-slate-400 shrink-0 mt-0.5" />
            <div className="flex flex-col">
              <span className="text-xxs uppercase tracking-wider font-semibold text-slate-400">Kontak</span>
              <span className="font-semibold text-slate-700">
                {booking.customer_phone}
                <br />
                <span className="text-xs text-slate-500 font-normal">{booking.customer_email}</span>
              </span>
            </div>
          </div>

          <div className="flex justify-between items-center border-t border-slate-100 pt-4 mt-2">
            <span className="text-sm font-bold text-slate-800">Total Harga</span>
            <span className="text-lg font-black text-emerald-600">
              {formatRupiah(booking.price)}
            </span>
          </div>
        </div>

        {/* Buttons */}
        <div className="flex flex-col gap-2">
          <Button onClick={() => navigate(`/${slug}`)} className="w-full py-3 justify-center">
            Pesan Lapangan Lain
          </Button>
          <Button onClick={() => navigate('/')} variant="secondary" className="w-full py-3 justify-center">
            Kembali ke Beranda
          </Button>
        </div>
      </div>
    </div>
  );
}
