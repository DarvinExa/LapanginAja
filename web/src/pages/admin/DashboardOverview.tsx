import { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { apiClient } from '../../api/client';
import { Skeleton } from '../../components/ui/Skeleton';
import { Button } from '../../components/ui/Button';
import { useToast } from '../../context/ToastContext';
import {
  CurrencyDollar,
  Percent,
  Clock,
  TennisBall,
} from '@phosphor-icons/react';
import { formatRupiah } from '../../utils/format';

interface StatsData {
  start_date: string;
  end_date: string;
  revenue: number;
  booked_hours: number;
  occupancy_rate: number;
  courts_count: number;
}

export default function DashboardOverview() {
  const { slug } = useParams<{ slug: string }>();
  const { addToast } = useToast();

  const [tenantId, setTenantId] = useState<number | null>(null);
  const [stats, setStats] = useState<StatsData | null>(null);
  const [startDate, setStartDate] = useState<string>('');
  const [endDate, setEndDate] = useState<string>('');
  
  const [isLoadingTenant, setIsLoadingTenant] = useState(true);
  const [isLoadingStats, setIsLoadingStats] = useState(false);

  // Set default dates (last 30 days)
  useEffect(() => {
    const today = new Date();
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);

    const formatDate = (d: Date) => {
      const yyyy = d.getFullYear();
      const mm = String(d.getMonth() + 1).padStart(2, '0');
      const dd = String(d.getDate()).padStart(2, '0');
      return `${yyyy}-${mm}-${dd}`;
    };

    setStartDate(formatDate(thirtyDaysAgo));
    setEndDate(formatDate(today));
  }, []);

  // Fetch Tenant to get ID
  useEffect(() => {
    const fetchTenant = async () => {
      try {
        const response = await apiClient.get(`/public/${slug}`);
        setTenantId(response.data.tenant.id);
      } catch {
        addToast('Gagal memuat detail tenant.', 'error');
      } finally {
        setIsLoadingTenant(false);
      }
    };
    if (slug) {
      fetchTenant();
    }
  }, [slug, addToast]);

  // Fetch Statistics
  const fetchStats = useCallback(async () => {
    if (!tenantId || !startDate || !endDate) return;
    setIsLoadingStats(true);
    try {
      const response = await apiClient.get(`/tenants/${tenantId}/stats`, {
        params: {
          start_date: startDate,
          end_date: endDate,
        },
      });
      setStats(response.data);
    } catch {
      addToast('Gagal mengambil data statistik.', 'error');
    } finally {
      setIsLoadingStats(false);
    }
  }, [tenantId, startDate, endDate, addToast]);

  useEffect(() => {
    fetchStats();
  }, [fetchStats]);

  if (isLoadingTenant) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        {Array.from({ length: 4 }).map((_, i) => (
          <Skeleton key={i} className="h-32 w-full rounded-none" />
        ))}
      </div>
    );
  }

  // Local formatters removed (using shared utils)

  return (
    <div className="flex flex-col gap-6">
      
      {/* Date Filter Panel */}
      <div className="bg-[#FDFBF7] border border-[#064E3B] p-4 rounded-none shadow-[4px_4px_0_#064E3B] flex flex-wrap items-center justify-between gap-4">
        <div>
          <h2 className="text-base font-bold text-[#064E3B]">Statistik Operasional</h2>
          <p className="text-xxs text-[#064E3B]/45 font-medium">Lihat okupansi dan performa keuangan venue Anda</p>
        </div>
        <div className="flex items-center gap-3">
          <div className="flex items-center gap-2">
            <span className="text-xs font-semibold text-[#064E3B]/65">Mulai:</span>
            <input
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className="px-2.5 py-1.5 text-xs border border-[#064E3B] rounded-none focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-[#064E3B]"
            />
          </div>
          <div className="flex items-center gap-2">
            <span className="text-xs font-semibold text-[#064E3B]/65">Sampai:</span>
            <input
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              className="px-2.5 py-1.5 text-xs border border-[#064E3B] rounded-none focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-[#064E3B]"
            />
          </div>
        </div>
      </div>

      {/* Promotion Link Card */}
      <div className="bg-[#10B981]/15 border border-[#064E3B] rounded-none p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-xs">
        <div className="flex flex-col gap-0.5">
          <span className="text-xs font-bold text-[#064E3B]">Link Pemesanan Publik</span>
          <span className="text-xxs text-[#10B981]">Bagikan tautan ini ke pelanggan untuk melakukan booking online</span>
          <span className="text-xs font-semibold text-[#064E3B] bg-[#FDFBF7]/75 border border-[#064E3B] rounded-none py-1.5 px-3 mt-1.5 w-fit select-all break-all">
            {window.location.origin}/{slug}
          </span>
        </div>
        <Button
          variant="secondary"
          className="bg-[#FDFBF7] border-[#064E3B] hover:bg-[#10B981]/25 text-[#064E3B] text-xs py-2 px-4 h-fit shrink-0 justify-center font-bold"
          onClick={() => {
            navigator.clipboard.writeText(`${window.location.origin}/${slug}`);
            addToast('Link pemesanan berhasil disalin!', 'success');
          }}
        >
          Salin Link
        </Button>
      </div>

      {/* Stats Cards Grid */}
      {isLoadingStats || !stats ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-32 w-full rounded-none" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
          
          {/* Card 1: Revenue */}
          <div className="bg-[#FDFBF7] border border-[#064E3B] p-5 rounded-none shadow-[4px_4px_0_#064E3B] flex items-center gap-4">
            <div className="p-3 bg-[#10B981]/15 rounded-none text-[#10B981]">
              <CurrencyDollar size={24} weight="duotone" />
            </div>
            <div className="flex flex-col">
              <span className="text-xxs font-bold text-[#064E3B]/45 uppercase tracking-wider">Total Pendapatan</span>
              <span className="text-xl font-extrabold text-[#064E3B] mt-0.5">
                {formatRupiah(stats.revenue)}
              </span>
            </div>
          </div>

          {/* Card 2: Occupancy Rate */}
          <div className="bg-[#FDFBF7] border border-[#064E3B] p-5 rounded-none shadow-[4px_4px_0_#064E3B] flex items-center gap-4">
            <div className="p-3 bg-[#10B981]/15 rounded-none text-[#064E3B]">
              <Percent size={24} weight="duotone" />
            </div>
            <div className="flex flex-col">
              <span className="text-xxs font-bold text-[#064E3B]/45 uppercase tracking-wider">Tingkat Okupansi</span>
              <span className="text-xl font-extrabold text-[#064E3B] mt-0.5">
                {stats.occupancy_rate}%
              </span>
            </div>
          </div>

          {/* Card 3: Booked Hours */}
          <div className="bg-[#FDFBF7] border border-[#064E3B] p-5 rounded-none shadow-[4px_4px_0_#064E3B] flex items-center gap-4">
            <div className="p-3 bg-indigo-50 rounded-none text-indigo-500">
              <Clock size={24} weight="duotone" />
            </div>
            <div className="flex flex-col">
              <span className="text-xxs font-bold text-[#064E3B]/45 uppercase tracking-wider">Durasi Terpesan</span>
              <span className="text-xl font-extrabold text-[#064E3B] mt-0.5">
                {stats.booked_hours} Jam
              </span>
            </div>
          </div>

          {/* Card 4: Active Courts */}
          <div className="bg-[#FDFBF7] border border-[#064E3B] p-5 rounded-none shadow-[4px_4px_0_#064E3B] flex items-center gap-4">
            <div className="p-3 bg-sky-50 rounded-none text-sky-500">
              <TennisBall size={24} weight="duotone" />
            </div>
            <div className="flex flex-col">
              <span className="text-xxs font-bold text-[#064E3B]/45 uppercase tracking-wider">Lapangan Aktif</span>
              <span className="text-xl font-extrabold text-[#064E3B] mt-0.5">
                {stats.courts_count} Lapangan
              </span>
            </div>
          </div>

        </div>
      )}
    </div>
  );
}
