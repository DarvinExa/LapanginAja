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
      <div className="bg-slate-50 border border-slate-200 rounded-xl p-5 text-center text-slate-500 text-sm">
        Pilih lapangan, tanggal, dan slot jam main untuk melihat ringkasan pesanan.
      </div>
    );
  }

  return (
    <div className="bg-white border border-slate-200 rounded-xl p-5 shadow-sm flex flex-col gap-4">
      <h3 className="text-sm font-bold text-slate-800 border-b border-slate-100 pb-2">
        Ringkasan Pesanan
      </h3>

      <div className="flex flex-col gap-2.5 text-sm">
        <div className="flex justify-between items-center">
          <span className="text-slate-500">Lapangan</span>
          <span className="font-semibold text-slate-800">{courtName}</span>
        </div>

        <div className="flex justify-between items-center">
          <span className="text-slate-500">Tanggal</span>
          <span className="font-semibold text-slate-800">
            {formatDateIndoLong(date)}
          </span>
        </div>

        <div className="flex justify-between items-center">
          <span className="text-slate-500">Waktu Main</span>
          <span className="font-semibold text-slate-800">
            {selectedSlot.start_time} - {selectedSlot.end_time}
          </span>
        </div>

        <div className="flex justify-between items-center border-t border-slate-100 pt-3 mt-1">
          <span className="text-base font-bold text-slate-800">Total Harga</span>
          <span className="text-lg font-bold text-emerald-600">
            {formatRupiah(selectedSlot.price)}
          </span>
        </div>
      </div>
    </div>
  );
};
