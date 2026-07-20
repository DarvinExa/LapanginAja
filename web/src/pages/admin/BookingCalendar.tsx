import { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { apiClient } from '../../api/client';
import { DatePicker } from '../../features/public-booking/DatePicker';
import { Skeleton } from '../../components/ui/Skeleton';
import { Badge } from '../../components/ui/Badge';
import { useToast } from '../../context/ToastContext';
import { Calendar, SoccerBall } from '@phosphor-icons/react';

interface Court {
  id: number;
  name: string;
  sport_type: string;
  is_active: boolean;
}

interface TimeSlot {
  start_time: string;
  end_time: string;
  status: 'booked' | 'available';
  price: number;
}

export default function BookingCalendar() {
  const { slug } = useParams<{ slug: string }>();
  const { addToast } = useToast();

  const [courts, setCourts] = useState<Court[]>([]);
  const [selectedCourt, setSelectedCourt] = useState<Court | null>(null);
  const [selectedDate, setSelectedDate] = useState<string>('');
  const [slots, setSlots] = useState<TimeSlot[]>([]);
  
  const [isLoadingCourts, setIsLoadingCourts] = useState(true);
  const [isLoadingSlots, setIsLoadingSlots] = useState(false);

  // Initialize date
  useEffect(() => {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    setSelectedDate(`${yyyy}-${mm}-${dd}`);
  }, []);

  // Fetch Courts
  useEffect(() => {
    const fetchCourts = async () => {
      try {
        const response = await apiClient.get(`/public/${slug}`);
        setCourts(response.data.courts);
        if (response.data.courts.length > 0) {
          setSelectedCourt(response.data.courts[0]);
        }
      } catch {
        addToast('Gagal memuat daftar lapangan.', 'error');
      } finally {
        setIsLoadingCourts(false);
      }
    };
    if (slug) {
      fetchCourts();
    }
  }, [slug, addToast]);

  // Fetch Slots availability
  const fetchSlots = useCallback(async () => {
    if (!selectedCourt || !selectedDate) return;
    setIsLoadingSlots(true);
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
      setIsLoadingSlots(false);
    }
  }, [slug, selectedCourt, selectedDate, addToast]);

  useEffect(() => {
    fetchSlots();
  }, [fetchSlots]);

  if (isLoadingCourts) {
    return (
      <div className="flex flex-col gap-6">
        <Skeleton className="h-10 w-48" />
        <Skeleton className="h-20 w-full" />
        <Skeleton className="h-60 w-full" />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      
      {/* Court Filter Header */}
      <div className="bg-[#FDFBF7] border border-[#064E3B] p-4 rounded-none shadow-[4px_4px_0_#064E3B] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div className="flex items-center gap-2">
          <Calendar size={24} className="text-[#10B981]" />
          <div>
            <h2 className="text-base font-bold text-[#064E3B]">Kalender Penjadwalan</h2>
            <p className="text-xxs text-[#064E3B]/45 font-medium">Monitoring ketersediaan slot harian venue</p>
          </div>
        </div>
        
        {/* Court Tabs */}
        <div className="flex gap-2 overflow-x-auto pb-1 sm:pb-0">
          {courts.map((court) => {
            const isSelected = selectedCourt?.id === court.id;
            return (
              <button
                key={court.id}
                onClick={() => setSelectedCourt(court)}
                className={`px-3 py-1.5 rounded-none text-xs font-semibold border transition-all duration-200 cursor-pointer ${
                  isSelected
                    ? 'bg-[#10B981] border-[#064E3B] text-white'
                    : 'bg-[#FDFBF7] border-[#064E3B] text-[#064E3B]/80 hover:bg-[#FDFBF7]'
                }`}
              >
                {court.name}
              </button>
            );
          })}
        </div>
      </div>

      {/* Date Slider */}
      <DatePicker
        selectedDate={selectedDate}
        onChange={setSelectedDate}
        maxAdvanceDays={30}
      />

      {/* Slots Timeline list */}
      <div className="bg-[#FDFBF7] border border-[#064E3B] rounded-none shadow-[4px_4px_0_#064E3B] overflow-hidden">
        <div className="px-5 py-4 border-b border-[#064E3B] bg-[#FDFBF7]/50">
          <h3 className="text-sm font-bold text-[#064E3B]">
            Jadwal Slot Lapangan: {selectedCourt?.name}
          </h3>
        </div>

        <div className="p-5">
          {isLoadingSlots ? (
            <div className="flex flex-col gap-3">
              {Array.from({ length: 5 }).map((_, i) => (
                <Skeleton key={i} className="h-12 w-full rounded-none" />
              ))}
            </div>
          ) : slots.length === 0 ? (
            <div className="text-center py-8 text-[#064E3B]/65 text-sm">
              Tidak ada jadwal operasional aktif pada tanggal ini.
            </div>
          ) : (
            <div className="flex flex-col gap-2.5">
              {slots.map((slot, index) => {
                const booked = slot.status === 'booked';
                return (
                  <div
                    key={index}
                    className={`flex items-center justify-between p-3.5 border rounded-none transition-all duration-150 ${
                      booked
                        ? 'bg-[#FDFBF7] border-[#064E3B]'
                        : 'bg-[#10B981]/10 border-[#064E3B] hover:bg-[#FDFBF7]'
                    }`}
                  >
                    <div className="flex items-center gap-3">
                      <SoccerBall
                        size={20}
                        className={booked ? 'text-[#064E3B]/45' : 'text-[#10B981]'}
                      />
                      <span className={`text-sm font-bold ${booked ? 'text-[#064E3B]/65' : 'text-[#064E3B]'}`}>
                        {slot.start_time} - {slot.end_time}
                      </span>
                    </div>

                    <Badge variant={booked ? 'neutral' : 'success'}>
                      {booked ? 'Terisi (Booked)' : 'Kosong (Available)'}
                    </Badge>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>

    </div>
  );
}
