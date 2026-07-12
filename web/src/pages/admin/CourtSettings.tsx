import { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { apiClient } from '../../api/client';
import { Skeleton } from '../../components/ui/Skeleton';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Modal } from '../../components/ui/Modal';
import { useToast } from '../../context/ToastContext';
import { TennisBall, Trash, Clock, Plus } from '@phosphor-icons/react';

interface OperatingHour {
  day_of_week: number;
  open_time: string;
  close_time: string;
  is_closed: boolean;
}

interface Court {
  id: number;
  name: string;
  sport_type: string;
  price_per_hour: number;
  slot_duration_minutes: number;
  is_active: boolean;
  operating_hours?: OperatingHour[];
}

export default function CourtSettings() {
  const { slug } = useParams<{ slug: string }>();
  const { addToast } = useToast();

  const [tenantId, setTenantId] = useState<number | null>(null);
  const [courts, setCourts] = useState<Court[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  // Modal court states
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [courtName, setCourtName] = useState('');
  const [sportType, setSportType] = useState('Futsal');
  const [pricePerHour, setPricePerHour] = useState('');
  const [slotDuration, setSlotDuration] = useState('60');
  const [isSubmittingCourt, setIsSubmittingCourt] = useState(false);

  // Modal hours states
  const [isHoursModalOpen, setIsHoursModalOpen] = useState(false);
  const [selectedCourtForHours, setSelectedCourtForHours] = useState<Court | null>(null);
  const [opHours, setOpHours] = useState<any[]>([]);
  const [isSavingHours, setIsSavingHours] = useState(false);

  // Fetch initial tenant
  const fetchTenant = useCallback(async () => {
    try {
      const response = await apiClient.get(`/public/${slug}`);
      setTenantId(response.data.tenant.id);
      setCourts(response.data.courts);
    } catch {
      addToast('Gagal memuat detail tenant.', 'error');
    } finally {
      setIsLoading(false);
    }
  }, [slug, addToast]);

  useEffect(() => {
    fetchTenant();
  }, [fetchTenant]);

  // Create court handler
  const handleCreateCourt = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!courtName.trim() || !pricePerHour.trim()) return;

    setIsSubmittingCourt(true);
    try {
      await apiClient.post(`/tenants/${tenantId}/courts`, {
        name: courtName,
        sport_type: sportType,
        price_per_hour: parseFloat(pricePerHour),
        slot_duration_minutes: parseInt(slotDuration),
        is_active: true,
      });

      addToast('Lapangan baru berhasil ditambahkan.', 'success');
      setIsModalOpen(false);
      
      // Reset form
      setCourtName('');
      setPricePerHour('');
      
      fetchTenant();
    } catch {
      addToast('Gagal membuat lapangan baru.', 'error');
    } finally {
      setIsSubmittingCourt(false);
    }
  };

  // Delete court handler
  const handleDeleteCourt = async (courtId: number) => {
    if (!window.confirm('Apakah Anda yakin ingin menghapus lapangan ini?')) return;

    try {
      await apiClient.delete(`/courts/${courtId}`);
      addToast('Lapangan berhasil dihapus.', 'success');
      fetchTenant();
    } catch {
      addToast('Gagal menghapus lapangan.', 'error');
    }
  };

  // Load and open operating hours modal
  const handleOpenHours = async (court: Court) => {
    setSelectedCourtForHours(court);
    setIsHoursModalOpen(true);
    
    // Use existing operating hours or fallback to default standard hours 08:00 - 22:00
    const defaults = Array.from({ length: 7 }).map((_, i) => {
      const existing = court.operating_hours?.find((oh) => oh.day_of_week === i);
      return {
        day_of_week: i,
        open_time: existing?.open_time || '08:00',
        close_time: existing?.close_time || '22:00',
        is_closed: existing ? Boolean(existing.is_closed) : false,
      };
    });
    setOpHours(defaults);
  };

  const handleUpdateOpHourRow = (index: number, key: string, value: any) => {
    const updated = [...opHours];
    updated[index] = { ...updated[index], [key]: value };
    setOpHours(updated);
  };

  const handleSaveOperatingHours = async () => {
    if (!selectedCourtForHours) return;
    setIsSavingHours(true);
    try {
      const payload = {
        hours: opHours.map((h) => ({
          day_of_week: h.day_of_week,
          open_time: h.open_time,
          close_time: h.close_time,
          is_closed: h.is_closed,
        })),
      };

      await apiClient.put(`/courts/${selectedCourtForHours.id}/operating-hours`, payload);
      addToast('Jam operasional berhasil disimpan.', 'success');
      setIsHoursModalOpen(false);
      fetchTenant(); // Refresh courts to get the updated operating hours
    } catch {
      addToast('Gagal menyimpan jam operasional.', 'error');
    } finally {
      setIsSavingHours(false);
    }
  };

  if (isLoading) {
    return (
      <div className="flex flex-col gap-6">
        <Skeleton className="h-10 w-48" />
        <Skeleton className="h-40 w-full" />
      </div>
    );
  }

  const formatRupiah = (amount: number) => {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0,
    }).format(amount);
  };

  const getDayLabel = (dayIndex: number) => {
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    return days[dayIndex];
  };

  return (
    <div className="flex flex-col gap-6">
      
      {/* Title Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <TennisBall size={24} className="text-emerald-600" />
          <div>
            <h2 className="text-base font-bold text-slate-800">Manajemen Lapangan</h2>
            <p className="text-xxs text-slate-400 font-medium">Kelola profil lapangan dan atur jam buka operasional harian</p>
          </div>
        </div>
        
        <Button onClick={() => setIsModalOpen(true)} icon={<Plus size={16} />}>
          Tambah Lapangan
        </Button>
      </div>

      {/* Courts list */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {courts.map((court) => (
          <div key={court.id} className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm flex flex-col justify-between gap-4">
            <div>
              <div className="flex justify-between items-start">
                <h3 className="font-bold text-slate-800 text-base">{court.name}</h3>
                <span className="px-2 py-0.5 text-xxs font-bold uppercase rounded-full bg-slate-100 text-slate-600">
                  {court.sport_type}
                </span>
              </div>
              <div className="flex flex-col gap-1 mt-3 text-xs text-slate-500">
                <div>
                  <span className="font-semibold text-slate-700">Tarif:</span> {formatRupiah(court.price_per_hour)}/jam
                </div>
                <div>
                  <span className="font-semibold text-slate-700">Durasi Slot:</span> {court.slot_duration_minutes} menit
                </div>
              </div>
            </div>

            <div className="flex gap-2 border-t border-slate-100 pt-3.5">
              <Button
                variant="secondary"
                className="flex-1 py-1.5 text-xs text-slate-700"
                icon={<Clock size={16} />}
                onClick={() => handleOpenHours(court)}
              >
                Jam Buka
              </Button>
              <button
                onClick={() => handleDeleteCourt(court.id)}
                className="px-3 py-1.5 border border-rose-200 hover:bg-rose-50 text-rose-600 rounded-lg transition-colors cursor-pointer focus:outline-none"
              >
                <Trash size={16} />
              </button>
            </div>
          </div>
        ))}
      </div>

      {/* Modal Add Court */}
      <Modal isOpen={isModalOpen} onClose={() => setIsModalOpen(false)} title="Tambah Lapangan Baru">
        <form onSubmit={handleCreateCourt} className="flex flex-col gap-4">
          <Input
            label="Nama Lapangan"
            type="text"
            required
            value={courtName}
            onChange={(e) => setCourtName(e.target.value)}
            placeholder="Contoh: Lapangan A"
          />

          <div className="flex flex-col gap-1">
            <label className="text-xs font-semibold text-slate-500">Jenis Olahraga</label>
            <select
              value={sportType}
              onChange={(e) => setSportType(e.target.value)}
              className="w-full px-3 py-2 text-sm bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              <option value="Futsal">Futsal</option>
              <option value="Badminton">Badminton</option>
              <option value="Basket">Basket</option>
              <option value="Tenis">Tenis</option>
            </select>
          </div>

          <Input
            label="Tarif per Jam (Rp)"
            type="number"
            required
            value={pricePerHour}
            onChange={(e) => setPricePerHour(e.target.value)}
            placeholder="Contoh: 100000"
          />

          <div className="flex flex-col gap-1">
            <label className="text-xs font-semibold text-slate-500">Durasi Slot per Booking (Menit)</label>
            <select
              value={slotDuration}
              onChange={(e) => setSlotDuration(e.target.value)}
              className="w-full px-3 py-2 text-sm bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              <option value="30">30 Menit</option>
              <option value="60">60 Menit</option>
              <option value="120">120 Menit</option>
            </select>
          </div>

          <div className="pt-2 flex justify-end gap-2">
            <Button variant="secondary" type="button" onClick={() => setIsModalOpen(false)}>
              Batal
            </Button>
            <Button type="submit" isLoading={isSubmittingCourt}>
              Simpan Lapangan
            </Button>
          </div>
        </form>
      </Modal>

      {/* Modal Edit Operating Hours */}
      <Modal
        isOpen={isHoursModalOpen}
        onClose={() => setIsHoursModalOpen(false)}
        title={`Atur Jam Buka: ${selectedCourtForHours?.name}`}
      >
        <div className="flex flex-col gap-4">
          <div className="flex flex-col gap-3">
            {opHours.map((hour, index) => (
              <div key={hour.day_of_week} className="flex items-center justify-between border-b border-slate-50 pb-2.5 last:border-0 gap-3">
                <span className="text-xs font-semibold text-slate-700 w-20">
                  {getDayLabel(hour.day_of_week)}
                </span>
                
                <div className="flex items-center gap-2">
                  <input
                    type="time"
                    disabled={hour.is_closed}
                    value={hour.open_time}
                    onChange={(e) => handleUpdateOpHourRow(index, 'open_time', e.target.value)}
                    className="px-2 py-1 text-xs border border-slate-200 rounded focus:outline-none disabled:bg-slate-100"
                  />
                  <span className="text-slate-400 text-xs">sampai</span>
                  <input
                    type="time"
                    disabled={hour.is_closed}
                    value={hour.close_time}
                    onChange={(e) => handleUpdateOpHourRow(index, 'close_time', e.target.value)}
                    className="px-2 py-1 text-xs border border-slate-200 rounded focus:outline-none disabled:bg-slate-100"
                  />
                </div>

                <label className="flex items-center gap-1 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={hour.is_closed}
                    onChange={(e) => handleUpdateOpHourRow(index, 'is_closed', e.target.checked)}
                    className="rounded text-emerald-600 focus:ring-emerald-500 h-3.5 w-3.5"
                  />
                  <span className="text-xxs font-bold text-rose-600">Tutup</span>
                </label>
              </div>
            ))}
          </div>

          <div className="pt-2 flex justify-end gap-2 border-t border-slate-100 pt-3">
            <Button variant="secondary" type="button" onClick={() => setIsHoursModalOpen(false)}>
              Batal
            </Button>
            <Button onClick={handleSaveOperatingHours} isLoading={isSavingHours}>
              Simpan Jadwal Buka
            </Button>
          </div>
        </div>
      </Modal>

    </div>
  );
}
