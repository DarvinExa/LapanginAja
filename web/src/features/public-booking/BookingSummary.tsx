import React from 'react';
import { type TimeSlot } from './SlotGrid';
import { formatRupiah, formatDateIndoLong } from '../../utils/format';

interface BookingSummaryProps {
  selectedSlot: TimeSlot | null;
  courtName: string | null;
  date: string;
}

export const BookingSummary: React.FC<BookingSummaryProps> = ({
  selectedSlot,
  courtName,
  date,
}) => {
  // Local formatters removed (using shared utils)

  if (!selectedSlot || !courtName) {
    return (
      <div className="bg-[#FDFBF7] border border-[#064E3B] rounded-none p-5 text-center text-[#064E3B]/65 text-sm">
        Pilih lapangan, tanggal, dan slot jam main untuk melihat ringkasan pesanan.
      </div>
    );
  }

  return (
    <div className="bg-[#FDFBF7] border border-[#064E3B] rounded-none p-5 shadow-[4px_4px_0_#064E3B] flex flex-col gap-4">
      <h3 className="text-sm font-bold text-[#064E3B] border-b border-[#064E3B] pb-2">
        Ringkasan Pesanan
      </h3>

      <div className="flex flex-col gap-2.5 text-sm">
        <div className="flex justify-between items-center">
          <span className="text-[#064E3B]/65">Lapangan</span>
          <span className="font-semibold text-[#064E3B]">{courtName}</span>
        </div>

        <div className="flex justify-between items-center">
          <span className="text-[#064E3B]/65">Tanggal</span>
          <span className="font-semibold text-[#064E3B]">
            {formatDateIndoLong(date)}
          </span>
        </div>

        <div className="flex justify-between items-center">
          <span className="text-[#064E3B]/65">Waktu Main</span>
          <span className="font-semibold text-[#064E3B]">
            {selectedSlot.start_time} - {selectedSlot.end_time}
          </span>
        </div>

        <div className="flex justify-between items-center border-t border-[#064E3B] pt-3 mt-1">
          <span className="text-base font-bold text-[#064E3B]">Total Harga</span>
          <span className="text-lg font-bold text-[#10B981]">
            {formatRupiah(selectedSlot.price)}
          </span>
        </div>
      </div>
    </div>
  );
};
