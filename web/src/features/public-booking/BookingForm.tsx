import React, { useState } from 'react';
import { Input } from '../../components/ui/Input';
import { Button } from '../../components/ui/Button';

interface BookingFormProps {
  onSubmit: (data: {
    customer_name: string;
    customer_phone: string;
    customer_email: string;
    notes: string;
  }) => void;
  isLoading?: boolean;
}

export const BookingForm: React.FC<BookingFormProps> = ({
  onSubmit,
  isLoading = false,
}) => {
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [notes, setNotes] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});

  const validate = () => {
    const tempErrors: Record<string, string> = {};

    if (!name.trim()) {
      tempErrors.name = 'Nama lengkap wajib diisi.';
    }

    if (!phone.trim()) {
      tempErrors.phone = 'Nomor telepon wajib diisi.';
    } else if (!/^[0-9+-\s]{8,15}$/.test(phone.trim())) {
      tempErrors.phone = 'Nomor telepon tidak valid (8 sampai 15 digit).';
    }

    if (!email.trim()) {
      tempErrors.email = 'Alamat email wajib diisi.';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      tempErrors.email = 'Alamat email tidak valid.';
    }

    setErrors(tempErrors);
    return Object.keys(tempErrors).length === 0;
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (validate()) {
      onSubmit({
        customer_name: name,
        customer_phone: phone,
        customer_email: email,
        notes,
      });
    }
  };

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      <h3 className="text-sm font-semibold text-slate-800">Data Pemesan</h3>

      <Input
        label="Nama lengkap"
        type="text"
        required
        value={name}
        onChange={(e) => setName(e.target.value)}
        placeholder="Masukkan nama lengkap Anda"
        error={errors.name}
      />

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Input
          label="Nomor WhatsApp"
          type="tel"
          required
          value={phone}
          onChange={(e) => setPhone(e.target.value)}
          placeholder="Contoh: 081234567890"
          error={errors.phone}
        />

        <Input
          label="Alamat email"
          type="email"
          required
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="contoh@domain.com"
          error={errors.email}
        />
      </div>

      <div className="flex flex-col gap-1.5 w-full">
        <label className="text-sm font-semibold text-slate-800">
          Catatan tambahan (opsional)
        </label>
        <textarea
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          rows={3}
          className="w-full px-3 py-2 text-sm bg-white border border-slate-200 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
          placeholder="Tulis catatan tambahan untuk pengelola..."
        />
      </div>

      <div className="pt-2">
        <Button
          type="submit"
          className="w-full justify-center py-3"
          isLoading={isLoading}
        >
          Booking Sekarang
        </Button>
      </div>
    </form>
  );
};
