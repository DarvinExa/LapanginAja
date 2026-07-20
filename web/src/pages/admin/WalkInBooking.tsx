import { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { apiClient } from '../../api/client';
import { DatePicker } from '../../features/public-booking/DatePicker';
import { SlotGrid, type TimeSlot } from '../../features/public-booking/SlotGrid';
import { Input } from '../../components/ui/Input';
import { Button } from '../../components/ui/Button';
import { Skeleton } from '../../components/ui/Skeleton';
import { useToast } from '../../context/ToastContext';
import { UserCheck } from '@phosphor-icons/react';

interface Court {
  id: number;
  name: string;
  sport_type: string;
  is_active: boolean;
}

export default function WalkInBooking() {
  const { slug } = useParams<{ slug: string }>();
  const { addToast } = useToast();

  const [tenantId, setTenantId] = useState<number | null>(null);
  const [courts, setCourts] = useState<Court[]>([]);
  const [selectedCourt, setSelectedCourt] = useState<Court | null>(null);
  const [selectedDate, setSelectedDate] = useState<string>('');
  const [slots, setSlots] = useState<TimeSlot[]>([]);
  const [selectedSlot, setSelectedSlot] = useState<TimeSlot | null>(null);

  // Form fields
  const [customerName, setCustomerName] = useState('');
  const [customerPhone, setCustomerPhone] = useState('');
  const [customerEmail, setCustomerEmail] = useState('');
  const [notes, setNotes] = useState('');

  const [isLoadingInit, setIsLoadingInit] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // 1. Fetch Tenant & Courts
  useEffect(() => {
    const fetchInit = async () => {
      try {
        const response = await apiClient.get(`/public/${slug}`);
        setTenantId(response.data.tenant.id);
        setCourts(response.data.courts);
        if (response.data.courts.length > 0) {
          setSelectedCourt(response.data.courts[0]);
        }

        // Set default date YYYY-MM-DD
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        setSelectedDate(`${yyyy}-${mm}-${dd}`);
      } catch {
        addToast('Gagal memuat detail tenant.', 'error');
      } finally {
        setIsLoadingInit(false);
      }
    };
    if (slug) {
      fetchInit();
    }
  }, [slug, addToast]);

  // 2. Fetch Availability Slots
  const fetchSlots = useCallback(async () => {
    if (!selectedCourt || !selectedDate) return;
    setIsSlotsLoading(true);
    setSelectedSlot(null);
    try {
      const response = await apiClient.get(`/public/${slug}/availability`, {
        params: {
          court_id: selectedCourt.id,
          date: selectedDate,
        },
      });
      setSlots(response.data.slots);
    } catch {
      addToast('Gagal mengambil ketersediaan slot.', 'error');
    } finally {
      setIsSlotsLoading(false);
    }
  }, [slug, selectedCourt, selectedDate, addToast]);

  // Separate loading state variable for ts compatibility
  const [isSlotsLoading, setIsSlotsLoading] = useState(false);

  useEffect(() => {
    fetchSlots();
  }, [fetchSlots]);

  // 3. Handle Submit
  const handleWalkInSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});

    if (!selectedCourt || !selectedSlot) {
      addToast('Silakan pilih lapangan dan slot waktu terlebih dahulu.', 'error');
      return;
    }

    const tempErrors: Record<string, string> = {};
    if (!customerName.trim()) tempErrors.name = 'Nama lengkap wajib diisi.';
    if (!customerPhone.trim()) tempErrors.phone = 'Nomor telepon wajib diisi.';
    
    if (Object.keys(tempErrors).length > 0) {
      setErrors(tempErrors);
      return;
    }

    setIsSubmitting(true);
    try {
      const payload = {
        court_id: selectedCourt.id,
        customer_name: customerName,
        customer_phone: customerPhone,
        customer_email: customerEmail || `${customerPhone}@walkin.lapanginaja.com`,
        start_time: `${selectedDate} ${selectedSlot.start_time}:00`,
        notes: notes,
      };

      await apiClient.post(`/tenants/${tenantId}/bookings/walk-in`, payload);
      addToast('Booking walk-in berhasil dibuat!', 'success');

      // Clear Form & selections
      setCustomerName('');
      setCustomerPhone('');
      setCustomerEmail('');
      setNotes('');
      setSelectedSlot(null);

      // Refresh slots
      fetchSlots();
    } catch (err: any) {
      addToast(err.response?.data?.message || 'Gagal membuat booking walk-in.', 'error');
    } finally {
      setIsSubmitting(false);
    }
  };

  if (isLoadingInit) {
    return (
      <div className="flex flex-col gap-6">
        <Skeleton className="h-10 w-48" />
        <Skeleton className="h-40 w-full" />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      
      {/* Title Header */}
      <div className="flex items-center gap-2">
        <UserCheck size={24} className="text-[#10B981]" />
        <div>
          <h2 className="text-base font-bold text-[#064E3B]">Booking Walk-In / Manual</h2>
          <p className="text-xxs text-[#064E3B]/45 font-medium">Buat pemesanan langsung di tempat untuk tamu offline</p>
        </div>
      </div>

      {/* Grid selector */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        
        {/* Selection side (left) */}
        <div className="lg:col-span-2 flex flex-col gap-5">
          {/* Court Tabs */}
          <div className="flex flex-col gap-2">
            <span className="text-sm font-semibold text-[#064E3B]">Pilih Lapangan</span>
            <div className="flex gap-2 overflow-x-auto pb-1">
              {courts.map((court) => {
                const isSelected = selectedCourt?.id === court.id;
                return (
                  <button
                    key={court.id}
                    onClick={() => setSelectedCourt(court)}
                    className={`px-4 py-2 rounded-none text-sm font-semibold border transition-all duration-200 cursor-pointer ${
                      isSelected
                        ? 'bg-[#10B981] border-[#064E3B] text-white shadow-[4px_4px_0_#064E3B]'
                        : 'bg-[#FDFBF7] border-[#064E3B] text-[#064E3B]/80 hover:bg-[#FDFBF7]'
                    }`}
                  >
                    {court.name}
                  </button>
                );
              })}
            </div>
          </div>

          {/* Date Picker */}
          <DatePicker
            selectedDate={selectedDate}
            onChange={setSelectedDate}
            maxAdvanceDays={30}
          />

          {/* Slots Grid */}
          <SlotGrid
            slots={slots}
            selectedSlot={selectedSlot}
            onSelectSlot={setSelectedSlot}
            isLoading={isSlotsLoading}
          />
        </div>

        {/* Walk-in Form (right) */}
        <div className="lg:col-span-1 bg-[#FDFBF7] border border-[#064E3B] p-5 rounded-none shadow-[4px_4px_0_#064E3B]">
          <h3 className="text-sm font-bold text-[#064E3B] border-b border-[#064E3B] pb-3 mb-4">
            Form Pemesanan Walk-In
          </h3>

          {selectedSlot ? (
            <form onSubmit={handleWalkInSubmit} className="flex flex-col gap-4">
              <div className="bg-[#FDFBF7] rounded-none p-3 text-xs border border-[#064E3B] flex flex-col gap-1 text-[#064E3B]/80">
                <div>
                  <span className="font-semibold text-[#064E3B] font-sans">Lapangan:</span> {selectedCourt?.name}
                </div>
                <div>
                  <span className="font-semibold text-[#064E3B] font-sans">Waktu:</span> {selectedDate} ({selectedSlot.start_time} - {selectedSlot.end_time})
                </div>
              </div>

              <Input
                label="Nama Pelanggan"
                type="text"
                required
                value={customerName}
                onChange={(e) => setCustomerName(e.target.value)}
                placeholder="Masukkan nama pelanggan"
                error={errors.name}
              />

              <Input
                label="Nomor telepon"
                type="tel"
                required
                value={customerPhone}
                onChange={(e) => setCustomerPhone(e.target.value)}
                placeholder="08xxxxxxxxxx"
                error={errors.phone}
              />

              <Input
                label="Email Pelanggan (opsional)"
                type="email"
                value={customerEmail}
                onChange={(e) => setCustomerEmail(e.target.value)}
                placeholder="pelanggan@domain.com"
              />

              <div className="flex flex-col gap-1.5 w-full">
                <label className="text-sm font-semibold text-[#064E3B]">Catatan</label>
                <textarea
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  rows={2}
                  className="w-full px-3 py-2 text-sm bg-[#FDFBF7] border border-[#064E3B] rounded-none focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-[#064E3B]"
                  placeholder="Catatan tambahan (pembayaran tunai dll)"
                />
              </div>

              <Button type="submit" className="w-full py-3 justify-center" isLoading={isSubmitting}>
                Konfirmasi Booking Offline
              </Button>
            </form>
          ) : (
            <div className="text-center text-[#064E3B]/65 text-xs py-8">
              Pilih slot waktu terlebih dahulu untuk menampilkan formulir pemesanan.
            </div>
          )}
        </div>

      </div>

    </div>
  );
}
