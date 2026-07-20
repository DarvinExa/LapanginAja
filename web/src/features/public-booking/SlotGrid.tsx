import React from 'react';
import { Skeleton } from '../../components/ui/Skeleton';
import { formatRupiah } from '../../utils/format';

export interface TimeSlot {
  start_time: string;
  end_time: string;
  status: 'booked' | 'available';
  price: number;
}

interface SlotGridProps {
  slots: TimeSlot[];
  selectedSlot: TimeSlot | null;
  onSelectSlot: (slot: TimeSlot) => void;
  isLoading?: boolean;
}

export const SlotGrid: React.FC<SlotGridProps> = ({
  slots,
  selectedSlot,
  onSelectSlot,
  isLoading = false,
}) => {
  if (isLoading) {
    return (
      <div className="flex flex-col gap-2">
        <span className="text-sm font-semibold text-[#064E3B]">
          Pilih Jam Main
        </span>
        <div className="grid grid-cols-3 sm:grid-cols-4 gap-3">
          {Array.from({ length: 8 }).map((_, i) => (
            <Skeleton key={i} className="h-14 w-full" />
          ))}
        </div>
      </div>
    );
  }

  if (slots.length === 0) {
    return (
      <div className="flex flex-col gap-2">
        <span className="text-sm font-semibold text-[#064E3B]">
          Pilih Jam Main
        </span>
        <div className="flex flex-col items-center justify-center p-8 bg-[#FDFBF7] border border-dashed border-[#064E3B] rounded-none text-center">
          <span className="text-sm font-semibold text-[#064E3B]/65">
            Tidak ada slot waktu operasional yang tersedia pada tanggal ini.
          </span>
          <span className="text-xs text-[#064E3B]/45 mt-1">
            Silakan pilih tanggal lain atau hubungi pengelola.
          </span>
        </div>
      </div>
    );
  }

  const isSelected = (slot: TimeSlot) => {
    return selectedSlot && selectedSlot.start_time === slot.start_time;
  };

  // Local formatters removed (using shared utils)

  return (
    <div className="flex flex-col gap-2">
      <span className="text-sm font-semibold text-[#064E3B]">
        Pilih Jam Main
      </span>
      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        {slots.map((slot, index) => {
          const booked = slot.status === 'booked';
          const selected = isSelected(slot);

          return (
            <button
              key={index}
              type="button"
              disabled={booked}
              onClick={() => onSelectSlot(slot)}
              className={`flex flex-col items-center justify-center p-3 rounded-none border text-center transition-all duration-150 focus:outline-none cursor-pointer ${
                selected
                  ? 'bg-[#10B981] border-[#064E3B] text-white shadow-[4px_4px_0_#064E3B] ring-2 ring-[#064E3B]/20'
                  : booked
                  ? 'bg-slate-100 border-[#064E3B] text-[#064E3B]/45 cursor-not-allowed opacity-60'
                  : 'bg-[#FDFBF7] border-[#064E3B] text-[#064E3B] hover:bg-[#10B981]/15 hover:border-[#064E3B]'
              }`}
            >
              <span className="text-sm font-bold">
                {slot.start_time} - {slot.end_time}
              </span>
              <span
                className={`text-xxs font-medium mt-1 ${
                  selected ? 'text-[#064E3B]' : booked ? 'text-[#064E3B]/45' : 'text-[#064E3B]'
                }`}
              >
                {booked ? 'Terisi' : formatRupiah(slot.price)}
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
};
