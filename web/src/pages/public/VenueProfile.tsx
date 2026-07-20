import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { apiClient } from '../../api/client';
import { DatePicker } from '../../features/public-booking/DatePicker';
import { SlotGrid, type TimeSlot } from '../../features/public-booking/SlotGrid';
import { BookingForm } from '../../features/public-booking/BookingForm';
import { BookingSummary } from '../../features/public-booking/BookingSummary';
import { Skeleton } from '../../components/ui/Skeleton';
import { useToast } from '../../context/ToastContext';

interface Tenant {
  id: number;
  name: string;
  slug: string;
  address: string;
  phone: string;
  timezone: string;
  hold_minutes: number;
  cancellation_window_hours: number;
  logo_url?: string | null;
  image_url?: string | null;
  description?: string | null;
}

interface Court {
  id: number;
  name: string;
  sport_type: string;
  price_per_hour: number;
  slot_duration_minutes: number;
  is_active: boolean;
}

export default function VenueProfile() {
  const { slug } = useParams<{ slug: string }>();
  const { addToast } = useToast();
  const navigate = useNavigate();

  const [tenant, setTenant] = useState<Tenant | null>(null);
  const [courts, setCourts] = useState<Court[]>([]);
  const [selectedCourt, setSelectedCourt] = useState<Court | null>(null);
  const [selectedDate, setSelectedDate] = useState<string>('');
  const [slots, setSlots] = useState<TimeSlot[]>([]);
  const [selectedSlot, setSelectedSlot] = useState<TimeSlot | null>(null);

  const [isVenueLoading, setIsVenueLoading] = useState(true);
  const [isSlotsLoading, setIsSlotsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // 1. Fetch Venue Profile and Courts
  useEffect(() => {
    const fetchVenue = async () => {
      setIsVenueLoading(true);
      setError(null);
      try {
        const response = await apiClient.get(`/public/${slug}`);
        setTenant(response.data.tenant);
        setCourts(response.data.courts);

        // Select first active court by default
        if (response.data.courts.length > 0) {
          setSelectedCourt(response.data.courts[0]);
        }

        // Default to today's date formatted as YYYY-MM-DD
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        setSelectedDate(`${yyyy}-${mm}-${dd}`);
      } catch (err: any) {
        setError(
          err.response?.data?.message ||
            'Gagal memuat profil venue. Pastikan alamat URL benar.'
        );
      } finally {
        setIsVenueLoading(false);
      }
    };

    if (slug) {
      fetchVenue();
    }
  }, [slug]);

  // 2. Fetch Availability Slots
  const fetchAvailability = useCallback(async () => {
    if (!selectedCourt || !selectedDate) return;
    setIsSlotsLoading(true);
    setSelectedSlot(null); // Reset selection on change
    try {
      const response = await apiClient.get(`/public/${slug}/availability`, {
        params: {
          court_id: selectedCourt.id,
          date: selectedDate,
        },
      });
      setSlots(response.data.slots);
    } catch (err: any) {
      addToast(
        err.response?.data?.message || 'Gagal mengambil jadwal ketersediaan.',
        'error'
      );
    } finally {
      setIsSlotsLoading(false);
    }
  }, [selectedCourt, selectedDate, slug, addToast]);

  useEffect(() => {
    fetchAvailability();
  }, [fetchAvailability]);

  // 3. Handle Booking Submission
  const handleBookingSubmit = async (formData: {
    customer_name: string;
    customer_phone: string;
    customer_email: string;
    notes: string;
  }) => {
    if (!selectedCourt || !selectedSlot) {
      addToast('Silakan pilih slot waktu terlebih dahulu.', 'error');
      return;
    }

    setIsSubmitting(true);
    try {
      const payload = {
        court_id: selectedCourt.id,
        customer_name: formData.customer_name,
        customer_phone: formData.customer_phone,
        customer_email: formData.customer_email,
        start_time: `${selectedDate} ${selectedSlot.start_time}:00`,
        notes: formData.notes,
      };

      const response = await apiClient.post(`/public/${slug}/bookings`, payload);
      const booking = response.data.booking;

      addToast('Pemesanan berhasil dibuat!', 'success');
      // Redirect to payment details (Batch 8 task flow)
      navigate(`/${slug}/bookings/${booking.booking_code}`);
    } catch (err: any) {
      addToast(
        err.response?.data?.message || 'Gagal memproses pembuatan booking.',
        'error'
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  if (isVenueLoading) {
    return (
      <div className="min-h-screen bg-[#FDFBF7] py-8 px-4 sm:px-6 lg:px-8">
        <div className="max-w-4xl mx-auto flex flex-col gap-6">
          <Skeleton className="h-32 w-full rounded-none" />
          <Skeleton className="h-10 w-48 rounded-none" />
          <Skeleton className="h-20 w-full rounded-none" />
          <Skeleton className="h-60 w-full rounded-none" />
        </div>
      </div>
    );
  }

  if (error || !tenant) {
    return (
      <div className="min-h-screen bg-[#FDFBF7] flex items-center justify-center p-4">
        <div className="bg-[#FDFBF7] border border-[#064E3B] p-8 rounded-none max-w-md w-full text-center shadow-[4px_4px_0_#064E3B]">
          <div className="text-rose-500 font-bold text-lg mb-2">Terjadi Kesalahan</div>
          <p className="text-[#064E3B]/80 text-sm mb-6">{error || 'Venue tidak ditemukan'}</p>
          <button
            onClick={() => window.location.reload()}
            className="px-4 py-2 bg-[#10B981] hover:bg-[#064E3B] text-white rounded-none text-sm font-medium transition-colors"
          >
            Coba Lagi
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-[#FDFBF7] py-8 px-4 sm:px-6 lg:px-8">
      <div className="max-w-4xl mx-auto flex flex-col gap-6">
        {/* Custom Venue Banner Cover */}
        {tenant.image_url && (
          <div className="w-full h-48 sm:h-64 rounded-none overflow-hidden border border-[#064E3B] shadow-[4px_4px_0_#064E3B] relative bg-slate-100">
            <img 
              src={tenant.image_url} 
              alt={tenant.name} 
              className="w-full h-full object-cover" 
              onError={(e) => {
                e.currentTarget.style.display = 'none';
              }}
            />
          </div>
        )}

        {/* Venue Header */}
        <div className="bg-[#FDFBF7] border border-[#064E3B] p-6 rounded-none shadow-[4px_4px_0_#064E3B] flex flex-col gap-4">
          <div className="flex items-center gap-4">
            {tenant.logo_url && (
              <div className="h-16 w-16 rounded-full overflow-hidden border border-[#064E3B] shrink-0 bg-[#FDFBF7] flex items-center justify-center">
                <img 
                  src={tenant.logo_url} 
                  alt="Logo" 
                  className="h-full w-full object-contain"
                  onError={(e) => {
                    e.currentTarget.style.display = 'none';
                  }}
                />
              </div>
            )}
            <div>
              <h1 className="text-2xl font-bold text-[#064E3B]">{tenant.name}</h1>
              <p className="text-sm text-[#064E3B]/65 mt-1">{tenant.address}</p>
            </div>
          </div>

          {tenant.description && (
            <p className="text-xs text-[#064E3B]/80 leading-relaxed border-t border-slate-50 pt-3">
              {tenant.description}
            </p>
          )}

          <div className="flex gap-4 pt-3 border-t border-[#064E3B] text-xs text-[#064E3B]/80">
            <div>
              <span className="font-semibold text-[#064E3B]">Telepon:</span> {tenant.phone}
            </div>
            <div>
              <span className="font-semibold text-[#064E3B]">Zona Waktu:</span> {tenant.timezone}
            </div>
          </div>
        </div>

        {/* Court Tabs Selector */}
        <div className="flex flex-col gap-2">
          <span className="text-sm font-semibold text-[#064E3B]">Pilih Lapangan</span>
          <div className="flex gap-2 overflow-x-auto pb-1">
            {courts.map((court) => {
              const isSelected = selectedCourt?.id === court.id;
              return (
                <button
                  key={court.id}
                  type="button"
                  onClick={() => setSelectedCourt(court)}
                  className={`px-4 py-2 rounded-none text-sm font-semibold border transition-all duration-200 cursor-pointer ${
                    isSelected
                      ? 'bg-[#10B981] border-[#064E3B] text-white shadow-[4px_4px_0_#064E3B]'
                      : 'bg-[#FDFBF7] border-[#064E3B] text-[#064E3B]/80 hover:bg-[#FDFBF7]'
                  }`}
                >
                  {court.name}
                  <span
                    className={`text-xxs font-normal ml-1.5 ${
                      isSelected ? 'text-[#FDFBF7]' : 'text-[#064E3B]/45'
                    }`}
                  >
                    ({court.sport_type})
                  </span>
                </button>
              );
            })}
          </div>
        </div>

        {/* Date Picker */}
        <DatePicker
          selectedDate={selectedDate}
          onChange={setSelectedDate}
          maxAdvanceDays={tenant.max_advance_days}
        />

        {/* Slots Grid */}
        <SlotGrid
          slots={slots}
          selectedSlot={selectedSlot}
          onSelectSlot={setSelectedSlot}
          isLoading={isSlotsLoading}
        />

        {/* Booking Checkout Panel (Summary & Form) */}
        {selectedSlot && (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-start mt-4">
            <div className="md:col-span-1">
              <BookingSummary
                selectedSlot={selectedSlot}
                courtName={selectedCourt?.name || ''}
                date={selectedDate}
              />
            </div>
            <div className="md:col-span-2 bg-[#FDFBF7] border border-[#064E3B] rounded-none p-5 shadow-[4px_4px_0_#064E3B]">
              <BookingForm onSubmit={handleBookingSubmit} isLoading={isSubmitting} />
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
