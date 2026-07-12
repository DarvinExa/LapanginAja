import { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { apiClient } from '../../api/client';
import { Skeleton } from '../../components/ui/Skeleton';
import { Input } from '../../components/ui/Input';
import { Button } from '../../components/ui/Button';
import { useToast } from '../../context/ToastContext';
import { Gear } from '@phosphor-icons/react';

interface TenantSettings {
  id: number;
  name: string;
  phone: string;
  address: string;
  hold_minutes: number;
  cancellation_window_hours: number;
  max_advance_days: number;
  logo_url?: string | null;
  image_url?: string | null;
  description?: string | null;
}

export default function VenueSettings() {
  const { slug } = useParams<{ slug: string }>();
  const { addToast } = useToast();

  const [settings, setSettings] = useState<TenantSettings | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);

  // Form states
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [address, setAddress] = useState('');
  const [timezone, setTimezone] = useState('Asia/Jakarta');
  const [logoUrl, setLogoUrl] = useState('');
  const [imageUrl, setImageUrl] = useState('');
  const [description, setDescription] = useState('');
  const [holdMinutes, setHoldMinutes] = useState('');
  const [cancellationHours, setCancellationHours] = useState('');
  const [maxAdvanceDays, setMaxAdvanceDays] = useState('');

  const fetchSettings = useCallback(async () => {
    try {
      const response = await apiClient.get(`/public/${slug}`);
      const tenant = response.data.tenant;
      setSettings(tenant);

      // Populate form
      setName(tenant.name);
      setPhone(tenant.phone);
      setAddress(tenant.address);
      setTimezone(tenant.timezone || 'Asia/Jakarta');
      setLogoUrl(tenant.logo_url || '');
      setImageUrl(tenant.image_url || '');
      setDescription(tenant.description || '');
      setHoldMinutes(String(tenant.hold_minutes));
      setCancellationHours(String(tenant.cancellation_window_hours));
      setMaxAdvanceDays(String(tenant.max_advance_days));
    } catch {
      addToast('Gagal memuat pengaturan venue.', 'error');
    } finally {
      setIsLoading(false);
    }
  }, [slug, addToast]);

  useEffect(() => {
    fetchSettings();
  }, [fetchSettings]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!settings) return;

    setIsSaving(true);
    try {
      await apiClient.put(`/tenants/${settings.id}`, {
        name,
        phone,
        address,
        timezone,
        logo_url: logoUrl || null,
        image_url: imageUrl || null,
        description: description || null,
        hold_minutes: parseInt(holdMinutes),
        cancellation_window_hours: parseInt(cancellationHours),
        max_advance_days: parseInt(maxAdvanceDays),
      });

      addToast('Pengaturan venue berhasil diperbarui.', 'success');
      fetchSettings();
    } catch {
      addToast('Gagal memperbarui pengaturan.', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return (
      <div className="flex flex-col gap-6">
        <Skeleton className="h-10 w-48" />
        <Skeleton className="h-60 w-full rounded-xl" />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      
      {/* Title Header */}
      <div className="flex items-center gap-2">
        <Gear size={24} className="text-emerald-600" />
        <div>
          <h2 className="text-base font-bold text-slate-800">Pengaturan Venue</h2>
          <p className="text-xxs text-slate-400 font-medium">Ubah detail profil venue, batas booking, dan durasi hold pembayaran</p>
        </div>
      </div>

      {/* Form Settings Card */}
      <div className="bg-white border border-slate-200 rounded-xl shadow-sm max-w-2xl w-full">
        <form onSubmit={handleSubmit} className="p-6 flex flex-col gap-5">
          
          <h3 className="text-sm font-bold text-slate-800 border-b border-slate-50 pb-2.5">
            Profil Venue
          </h3>

          <Input
            label="Nama Venue"
            type="text"
            required
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Masukkan nama venue"
          />

          <Input
            label="Nomor Telepon Venue"
            type="tel"
            required
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            placeholder="Contoh: 0812345678"
          />

          <Input
            label="Alamat Fisik Venue"
            type="text"
            required
            value={address}
            onChange={(e) => setAddress(e.target.value)}
            placeholder="Masukkan alamat lengkap venue"
          />

          <Input
            label="URL Logo Venue (Opsional)"
            type="text"
            value={logoUrl}
            onChange={(e) => setLogoUrl(e.target.value)}
            placeholder="Contoh: https://domain.com/logo.png"
            helperText="Tautan logo kustom untuk dipasang di header profil publik."
          />

          <Input
            label="URL Gambar Sampul/Banner Venue (Opsional)"
            type="text"
            value={imageUrl}
            onChange={(e) => setImageUrl(e.target.value)}
            placeholder="Contoh: https://domain.com/banner.jpg"
            helperText="Gambar latar belakang premium untuk sampul profil publik."
          />

          <div className="flex flex-col gap-1">
            <label className="text-xs font-semibold text-slate-500">Deskripsi / Tentang Bisnis (Opsional)</label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Ceritakan tentang bisnis lapangan olahraga Anda..."
              rows={3}
              className="w-full px-3 py-2 text-sm bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>

          <div className="flex flex-col gap-1">
            <label className="text-xs font-semibold text-slate-500">Zona Waktu Venue</label>
            <select
              value={timezone}
              onChange={(e) => setTimezone(e.target.value)}
              className="w-full px-3 py-2 text-sm bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
              <option value="Asia/Jakarta">WIB (Asia/Jakarta)</option>
              <option value="Asia/Makassar">WITA (Asia/Makassar)</option>
              <option value="Asia/Jayapura">WIT (Asia/Jayapura)</option>
            </select>
          </div>

          <h3 className="text-sm font-bold text-slate-800 border-b border-slate-50 pb-2.5 mt-2">
            Kebijakan & Batasan Pemesanan
          </h3>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label="Batas Hold Pembayaran (Menit)"
              type="number"
              required
              value={holdMinutes}
              onChange={(e) => setHoldMinutes(e.target.value)}
              placeholder="Contoh: 15"
              helperText="Durasi sistem menahan slot jam sebelum dibatalkan otomatis jika belum dibayar."
            />

            <Input
              label="Batas Pembatalan Refund (Jam)"
              type="number"
              required
              value={cancellationHours}
              onChange={(e) => setCancellationHours(e.target.value)}
              placeholder="Contoh: 2"
              helperText="Batas waktu minimal pembatalan pesanan sebelum jadwal dimulai agar dana di-refund."
            />
          </div>

          <Input
            label="Batas Maksimal Booking ke Depan (Hari)"
            type="number"
            required
            value={maxAdvanceDays}
            onChange={(e) => setMaxAdvanceDays(e.target.value)}
            placeholder="Contoh: 30"
            helperText="Jumlah hari ke depan maksimal yang bisa dipesan oleh pemain."
          />

          <div className="pt-3 border-t border-slate-100 flex justify-end gap-2.5">
            <Button type="submit" isLoading={isSaving} className="px-6 py-2.5">
              Simpan Perubahan
            </Button>
          </div>

        </form>
      </div>

    </div>
  );
}
