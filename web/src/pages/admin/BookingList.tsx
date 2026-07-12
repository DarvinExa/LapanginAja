import { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { apiClient } from '../../api/client';
import { Skeleton } from '../../components/ui/Skeleton';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { useToast } from '../../context/ToastContext';
import { ClipboardText, CaretLeft, CaretRight } from '@phosphor-icons/react';
import { formatRupiah } from '../../utils/format';

interface Booking {
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
  court: {
    name: string;
    sport_type: string;
  };
}

export default function BookingList() {
  const { slug } = useParams<{ slug: string }>();
  const { addToast } = useToast();

  const [tenantId, setTenantId] = useState<number | null>(null);
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [courts, setCourts] = useState<{ id: number; name: string }[]>([]);
  
  // Filters state
  const [selectedCourt, setSelectedCourt] = useState<string>('');
  const [selectedStatus, setSelectedStatus] = useState<string>('');
  const [selectedPaymentStatus, setSelectedPaymentStatus] = useState<string>('');
  
  // Pagination state
  const [currentPage, setCurrentPage] = useState<number>(1);
  const [lastPage, setLastPage] = useState<number>(1);
  
  const [isLoadingTenant, setIsLoadingTenant] = useState(true);
  const [isLoadingBookings, setIsLoadingBookings] = useState(false);
  const [isUpdatingStatus, setIsUpdatingStatus] = useState<number | null>(null);

  // 1. Fetch Tenant & Courts first
  useEffect(() => {
    const fetchInitData = async () => {
      try {
        const response = await apiClient.get(`/public/${slug}`);
        setTenantId(response.data.tenant.id);
        setCourts(response.data.courts);
      } catch {
        addToast('Gagal memuat data awal.', 'error');
      } finally {
        setIsLoadingTenant(false);
      }
    };
    if (slug) {
      fetchInitData();
    }
  }, [slug, addToast]);

  // 2. Fetch Bookings list
  const fetchBookings = useCallback(async () => {
    if (!tenantId) return;
    setIsLoadingBookings(true);
    try {
      const response = await apiClient.get(`/tenants/${tenantId}/bookings`, {
        params: {
          page: currentPage,
          court_id: selectedCourt || undefined,
          status: selectedStatus || undefined,
          payment_status: selectedPaymentStatus || undefined,
        },
      });
      setBookings(response.data.data);
      setCurrentPage(response.data.meta.current_page);
      setLastPage(response.data.meta.last_page);
    } catch {
      addToast('Gagal mengambil daftar bookings.', 'error');
    } finally {
      setIsLoadingBookings(false);
    }
  }, [tenantId, currentPage, selectedCourt, selectedStatus, selectedPaymentStatus, addToast]);

  useEffect(() => {
    fetchBookings();
  }, [fetchBookings]);

  // Reset page when filter changes
  useEffect(() => {
    setCurrentPage(1);
  }, [selectedCourt, selectedStatus, selectedPaymentStatus]);

  // 3. Update Booking Status
  const handleUpdateStatus = async (bookingId: number, nextStatus: string) => {
    setIsUpdatingStatus(bookingId);
    try {
      await apiClient.patch(`/bookings/${bookingId}/status`, {
        status: nextStatus,
      });
      addToast('Status booking berhasil diperbarui.', 'success');
      // Refresh list
      fetchBookings();
    } catch {
      addToast('Gagal memperbarui status booking.', 'error');
    } finally {
      setIsUpdatingStatus(null);
    }
  };

  if (isLoadingTenant) {
    return (
      <div className="flex flex-col gap-6">
        <Skeleton className="h-12 w-full rounded-xl" />
        <Skeleton className="h-60 w-full rounded-xl" />
      </div>
    );
  }

  // Local formatters removed (using shared utils)

  const getStatusVariant = (s: string) => {
    switch (s) {
      case 'confirmed':
      case 'completed':
        return 'success';
      case 'pending':
        return 'warning';
      case 'cancelled':
        return 'error';
      default:
        return 'neutral';
    }
  };

  const getPaymentVariant = (p: string) => {
    switch (p) {
      case 'paid':
        return 'success';
      case 'unpaid':
        return 'warning';
      case 'failed':
      case 'refunded':
        return 'error';
      default:
        return 'neutral';
    }
  };

  return (
    <div className="flex flex-col gap-6">
      
      {/* Title Header */}
      <div className="flex items-center gap-2">
        <ClipboardText size={24} className="text-emerald-600" />
        <div>
          <h2 className="text-base font-bold text-slate-800">Manajemen Pemesanan</h2>
          <p className="text-xxs text-slate-400 font-medium">Lihat, saring, dan perbarui seluruh riwayat booking lapangan</p>
        </div>
      </div>

      {/* Filters Panel */}
      <div className="bg-white border border-slate-200 p-4 rounded-xl shadow-sm flex flex-wrap gap-4 items-center">
        {/* Court Filter */}
        <div className="flex flex-col gap-1">
          <span className="text-xs font-semibold text-slate-500">Filter Lapangan</span>
          <select
            value={selectedCourt}
            onChange={(e) => setSelectedCourt(e.target.value)}
            className="px-3 py-1.5 text-xs bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500"
          >
            <option value="">Semua Lapangan</option>
            {courts.map((court) => (
              <option key={court.id} value={court.id}>
                {court.name}
              </option>
            ))}
          </select>
        </div>

        {/* Status Filter */}
        <div className="flex flex-col gap-1">
          <span className="text-xs font-semibold text-slate-500">Status Booking</span>
          <select
            value={selectedStatus}
            onChange={(e) => setSelectedStatus(e.target.value)}
            className="px-3 py-1.5 text-xs bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500"
          >
            <option value="">Semua Status</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="cancelled">Cancelled</option>
            <option value="completed">Completed</option>
          </select>
        </div>

        {/* Payment Status Filter */}
        <div className="flex flex-col gap-1">
          <span className="text-xs font-semibold text-slate-500">Status Pembayaran</span>
          <select
            value={selectedPaymentStatus}
            onChange={(e) => setSelectedPaymentStatus(e.target.value)}
            className="px-3 py-1.5 text-xs bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500"
          >
            <option value="">Semua Pembayaran</option>
            <option value="unpaid">Belum Bayar (Unpaid)</option>
            <option value="paid">Lunas (Paid)</option>
            <option value="refunded">Refunded</option>
            <option value="failed">Gagal (Failed)</option>
          </select>
        </div>
      </div>

      {/* Bookings Table Card */}
      <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-slate-50 border-b border-slate-100 text-xxs font-bold uppercase tracking-wider text-slate-400">
                <th className="px-5 py-3">Kode / Lapangan</th>
                <th className="px-5 py-3">Pemain / Kontak</th>
                <th className="px-5 py-3">Waktu Main</th>
                <th className="px-5 py-3 text-right">Harga</th>
                <th className="px-5 py-3 text-center">Status</th>
                <th className="px-5 py-3 text-center">Pembayaran</th>
                <th className="px-5 py-3 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-xs">
              {isLoadingBookings ? (
                Array.from({ length: 5 }).map((_, i) => (
                  <tr key={i}>
                    <td colSpan={7} className="px-5 py-4">
                      <Skeleton className="h-6 w-full rounded" />
                    </td>
                  </tr>
                ))
              ) : bookings.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-8 text-center text-slate-500">
                    Tidak ditemukan data booking yang sesuai dengan kriteria filter.
                  </td>
                </tr>
              ) : (
                bookings.map((booking) => (
                  <tr key={booking.id} className="hover:bg-slate-50/50">
                    <td className="px-5 py-4">
                      <span className="font-bold text-slate-800 select-all">{booking.booking_code}</span>
                      <div className="text-xxs text-slate-400 mt-0.5">{booking.court.name}</div>
                    </td>
                    <td className="px-5 py-4">
                      <span className="font-semibold text-slate-700">{booking.customer_name}</span>
                      <div className="text-xxs text-slate-400 mt-0.5">{booking.customer_phone}</div>
                    </td>
                    <td className="px-5 py-4">
                      <span>{booking.start_time.split(' ')[0]}</span>
                      <div className="text-xxs text-slate-500 font-medium mt-0.5">
                        {booking.start_time.split(' ')[1]?.substring(0, 5)} - {booking.end_time.split(' ')[1]?.substring(0, 5)}
                      </div>
                    </td>
                    <td className="px-5 py-4 text-right font-semibold text-slate-800">
                      {formatRupiah(booking.price)}
                    </td>
                    <td className="px-5 py-4 text-center">
                      <Badge variant={getStatusVariant(booking.status)}>
                        {booking.status}
                      </Badge>
                    </td>
                    <td className="px-5 py-4 text-center">
                      <Badge variant={getPaymentVariant(booking.payment_status)}>
                        {booking.payment_status}
                      </Badge>
                    </td>
                    <td className="px-5 py-4 text-center">
                      <div className="flex items-center justify-center gap-2">
                        {booking.status === 'confirmed' && (
                          <Button
                            variant="primary"
                            className="px-2.5 py-1 text-xxs rounded"
                            onClick={() => handleUpdateStatus(booking.id, 'completed')}
                            isLoading={isUpdatingStatus === booking.id}
                          >
                            Selesai Main
                          </Button>
                        )}
                        {booking.status === 'pending' && (
                          <Button
                            variant="destructive"
                            className="px-2.5 py-1 text-xxs rounded"
                            onClick={() => handleUpdateStatus(booking.id, 'cancelled')}
                            isLoading={isUpdatingStatus === booking.id}
                          >
                            Batalkan
                          </Button>
                        )}
                        {booking.status !== 'confirmed' && booking.status !== 'pending' && (
                          <span className="text-slate-400 text-xxs font-medium">-</span>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination Toolbar */}
        {lastPage > 1 && (
          <div className="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
            <span className="text-xxs text-slate-400 font-semibold uppercase">
              Halaman {currentPage} dari {lastPage}
            </span>
            <div className="flex items-center gap-1.5">
              <Button
                variant="secondary"
                className="px-3 py-1.5 text-xs rounded-lg"
                disabled={currentPage <= 1 || isLoadingBookings}
                onClick={() => setCurrentPage((prev) => prev - 1)}
                icon={<CaretLeft size={16} />}
              >
                Sebelumnya
              </Button>
              <Button
                variant="secondary"
                className="px-3 py-1.5 text-xs rounded-lg"
                disabled={currentPage >= lastPage || isLoadingBookings}
                onClick={() => setCurrentPage((prev) => prev + 1)}
                icon={<CaretRight size={16} />}
              >
                Selanjutnya
              </Button>
            </div>
          </div>
        )}
      </div>

    </div>
  );
}
