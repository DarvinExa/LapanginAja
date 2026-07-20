import React from 'react';

interface DatePickerProps {
  selectedDate: string;
  onChange: (date: string) => void;
  maxAdvanceDays?: number;
}

export const DatePicker: React.FC<DatePickerProps> = ({
  selectedDate,
  onChange,
  maxAdvanceDays = 30,
}) => {
  const getDaysArray = () => {
    const days = [];
    const today = new Date();

    for (let i = 0; i < maxAdvanceDays; i++) {
      const date = new Date(today);
      date.setDate(today.getDate() + i);
      days.push(date);
    }
    return days;
  };

  const days = getDaysArray();

  const formatDateValue = (date: Date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  const getDayName = (date: Date) => {
    const today = new Date();
    const tomorrow = new Date();
    tomorrow.setDate(today.getDate() + 1);

    if (date.toDateString() === today.toDateString()) {
      return 'Hari ini';
    }
    if (date.toDateString() === tomorrow.toDateString()) {
      return 'Besok';
    }

    const dayIndex = date.getDay();
    const names = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    return names[dayIndex];
  };

  const getMonthName = (date: Date) => {
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'Mei',
      'Jun',
      'Jul',
      'Agu',
      'Sep',
      'Okt',
      'Nov',
      'Des',
    ];
    return months[date.getMonth()];
  };

  return (
    <div className="flex flex-col gap-2">
      <span className="text-sm font-semibold text-[#064E3B]">Pilih Tanggal</span>
      <div className="flex gap-2 overflow-x-auto pb-2 scrollbar-thin scrollbar-thumb-slate-200">
        {days.map((date, index) => {
          const val = formatDateValue(date);
          const isSelected = val === selectedDate;

          return (
            <button
              key={index}
              type="button"
              onClick={() => onChange(val)}
              className={`flex flex-col items-center justify-center p-3 rounded-none border min-w-[70px] transition-all duration-200 cursor-pointer ${
                isSelected
                  ? 'bg-[#10B981] border-[#064E3B] text-white shadow-[4px_4px_0_#064E3B]'
                  : 'bg-[#FDFBF7] border-[#064E3B] text-[#064E3B] hover:bg-[#FDFBF7]'
              }`}
            >
              <span
                className={`text-xxs uppercase tracking-wider font-semibold ${
                  isSelected ? 'text-[#FDFBF7]' : 'text-[#064E3B]/45'
                }`}
              >
                {getDayName(date)}
              </span>
              <span className="text-lg font-bold mt-0.5">{date.getDate()}</span>
              <span
                className={`text-xxs font-medium mt-0.5 ${
                  isSelected ? 'text-[#FDFBF7]' : 'text-[#064E3B]/45'
                }`}
              >
                {getMonthName(date)}
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
};
