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
      <div className="bg-white border border-slate-200 p-4 rounded-xl shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div className="flex items-center gap-2">
          <Calendar size={24} className="text-emerald-600" />
          <div>
            <h2 className="text-base font-bold text-slate-800">Kalender Penjadwalan</h2>
            <p className="text-xxs text-slate-400 font-medium">Monitoring ketersediaan slot harian venue</p>
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
                className={`px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all duration-200 cursor-pointer ${
                  isSelected
                    ? 'bg-emerald-600 border-emerald-600 text-white'
                    : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
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
      <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div className="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
          <h3 className="text-sm font-bold text-slate-800">
            Jadwal Slot Lapangan: {selectedCourt?.name}
          </h3>
        </div>

        <div className="p-5">
          {isLoadingSlots ? (
            <div className="flex flex-col gap-3">
              {Array.from({ length: 5 }).map((_, i) => (
                <Skeleton key={i} className="h-12 w-full rounded-lg" />
              ))}
            </div>
          ) : slots.length === 0 ? (
            <div className="text-center py-8 text-slate-500 text-sm">
              Tidak ada jadwal operasional aktif pada tanggal ini.
            </div>
          ) : (
            <div className="flex flex-col gap-2.5">
              {slots.map((slot, index) => {
                const booked = slot.status === 'booked';
                return (
                  <div
                    key={index}
                    className={`flex items-center justify-between p-3.5 border rounded-lg transition-all duration-150 ${
                      booked
                        ? 'bg-slate-50 border-slate-200'
                        : 'bg-emerald-50/10 border-emerald-100 hover:bg-emerald-50/20'
                    }`}
                  >
                    <div className="flex items-center gap-3">
                      <SoccerBall
                        size={20}
                        className={booked ? 'text-slate-400' : 'text-emerald-500'}
                      />
                      <span className={`text-sm font-bold ${booked ? 'text-slate-500' : 'text-slate-800'}`}>
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
