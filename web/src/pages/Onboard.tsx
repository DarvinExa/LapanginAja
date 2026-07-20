import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiClient } from '../api/client';
import { Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { useToast } from '../context/ToastContext';
import { useAuth } from '../context/AuthContext';
import { SoccerBall } from '@phosphor-icons/react';

export default function Onboard() {
  const navigate = useNavigate();
  const { addToast } = useToast();
  const { refreshProfile, user } = useAuth();

  const [name, setName] = useState('');
  const [slug, setSlug] = useState('');
  const [address, setAddress] = useState('');
  const [phone, setPhone] = useState('');
  const [timezone, setTimezone] = useState('Asia/Jakarta');

  const [isLoading, setIsLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Satu owner hanya boleh memiliki satu bisnis. Kalau akun ini sudah punya
  // bisnis, jangan tampilkan form onboarding lagi; langsung arahkan ke
  // dashboard bisnis yang sudah ada.
  useEffect(() => {
    const existingTenant =
      user?.tenants && user.tenants.length > 0 ? user.tenants[0] : null;
    if (existingTenant) {
      navigate(`/admin/${existingTenant.slug}`, { replace: true });
    }
  }, [user, navigate]);

  const handleSlugify = (val: string) => {
    setName(val);
    // Automatically generate clean slug
    const cleanSlug = val
      .toLowerCase()
      .trim()
      .replace(/[^\w\s-]/g, '')
      .replace(/[\s_-]+/g, '-')
      .replace(/^-+|-+$/g, '');
    setSlug(cleanSlug);
  };

  const handleOnboardSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});
    setIsLoading(true);

    try {
      await apiClient.post('/tenants', {
        name,
        slug,
        address,
        phone,
        timezone,
      });

      // Refresh owner profile state to populate their newly created tenant info
      await refreshProfile();

      addToast('Venue berhasil didaftarkan!', 'success');
      // Redirect to admin dashboard of the venue
      navigate(`/admin/${slug}`);
    } catch (err: any) {
      // Backend menolak karena akun sudah memiliki bisnis: arahkan ke dashboard.
      if (err.response?.status === 409 && err.response?.data?.tenant?.slug) {
        addToast(
          err.response.data.message || 'Anda sudah memiliki bisnis.',
          'warning'
        );
        navigate(`/admin/${err.response.data.tenant.slug}`, { replace: true });
        return;
      }
      if (err.response?.data?.errors) {
        const valErrors: Record<string, string> = {};
        Object.keys(err.response.data.errors).forEach((key) => {
          valErrors[key] = err.response.data.errors[key][0];
        });
        setErrors(valErrors);
      } else {
        addToast(
          err.response?.data?.message || 'Gagal mendaftarkan venue. Silakan coba lagi.',
          'error'
        );
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#FDFBF7] flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full mx-auto flex flex-col gap-6">
        
        {/* Logo and title */}
        <div className="text-center flex flex-col items-center gap-2">
          <SoccerBall size={48} className="text-[#10B981]" weight="fill" />
          <h2 className="text-2xl font-black text-[#064E3B] tracking-tight">
            Daftarkan Venue Olahraga Anda
          </h2>
          <p className="text-xs text-[#064E3B]/65 max-w-xs mx-auto">
            Langkah terakhir untuk mengaktifkan dashboard SaaS khusus venue Anda.
          </p>
        </div>

        {/* Card Form */}
        <div className="bg-[#FDFBF7] border border-[#064E3B] p-6 rounded-none shadow-[4px_4px_0_#064E3B]">
          <form onSubmit={handleOnboardSubmit} className="flex flex-col gap-4">
            
            <Input
              label="Nama Venue"
              type="text"
              required
              value={name}
              onChange={(e) => handleSlugify(e.target.value)}
              placeholder="Contoh: Senayan Sport Arena"
              error={errors.name}
            />

            <Input
              label="Slug URL Venue"
              type="text"
              required
              value={slug}
              onChange={(e) => setSlug(e.target.value)}
              placeholder="contoh-slug-venue"
              error={errors.slug}
              helperText={`Akan menjadi alamat URL Anda: lapanginaja.com/${slug || 'nama-venue'}`}
            />

            <Input
              label="Alamat Fisik Venue"
              type="text"
              required
              value={address}
              onChange={(e) => setAddress(e.target.value)}
              placeholder="Masukkan alamat lengkap venue"
              error={errors.address}
            />

            <Input
              label="Nomor Telepon Venue"
              type="tel"
              required
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              placeholder="Contoh: 081234567890"
              error={errors.phone}
            />

            <div className="flex flex-col gap-1">
              <label className="text-xs font-semibold text-[#064E3B]/65">Zona Waktu</label>
              <select
                value={timezone}
                onChange={(e) => setTimezone(e.target.value)}
                className="w-full px-3 py-2 text-sm bg-[#FDFBF7] border border-[#064E3B] rounded-none focus:outline-none focus:ring-1 focus:ring-emerald-500"
              >
                <option value="Asia/Jakarta">WIB (Asia/Jakarta)</option>
                <option value="Asia/Makassar">WITA (Asia/Makassar)</option>
                <option value="Asia/Jayapura">WIT (Asia/Jayapura)</option>
              </select>
            </div>

            <Button type="submit" className="w-full py-3 justify-center mt-2" isLoading={isLoading}>
              Selesaikan Pendaftaran
            </Button>
          </form>
        </div>

      </div>
    </div>
  );
}
