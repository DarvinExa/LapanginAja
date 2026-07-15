import { Link } from 'react-router-dom';
import { Button } from '../components/ui/Button';
import { useAuth } from '../context/AuthContext';
import {
  SoccerBall,
  CalendarCheck,
  CreditCard,
  ShieldCheck,
  House,
  DeviceMobile,
  Lightning,
  CheckCircle,
  Storefront,
  QrCode,
  Receipt,
  MapPin,
  ChartLineUp,
  Clock,
} from '@phosphor-icons/react';

const previewSlots = [
  { time: '08:00', state: 'available' as const },
  { time: '09:00', state: 'booked' as const },
  { time: '10:00', state: 'selected' as const },
  { time: '11:00', state: 'available' as const },
  { time: '13:00', state: 'available' as const },
  { time: '14:00', state: 'booked' as const },
  { time: '15:00', state: 'available' as const },
  { time: '16:00', state: 'available' as const },
  { time: '17:00', state: 'booked' as const },
];

const slotStyles: Record<string, string> = {
  available: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  booked: 'border-slate-200 bg-slate-100 text-slate-400',
  selected: 'border-orange-400 bg-orange-500 text-white shadow-sm',
};

const paymentMethods = ['QRIS', 'GoPay', 'ShopeePay', 'Transfer Bank', 'Virtual Account'];

const features = [
  {
    icon: CalendarCheck,
    accent: 'bg-emerald-50 text-emerald-600',
    title: 'Pemesanan Real Time',
    body: 'Pilih slot waktu lapangan yang tersedia dan kunci seketika. Sistem mencegah bentrok jadwal secara otomatis, tanpa perlu chat admin manual.',
  },
  {
    icon: CreditCard,
    accent: 'bg-orange-50 text-orange-500',
    title: 'Pembayaran Otomatis',
    body: 'Terhubung dengan Midtrans untuk pembayaran instan. Pelanggan bisa membayar lunas lewat QRIS, GoPay, ShopeePay, atau transfer bank.',
  },
  {
    icon: ShieldCheck,
    accent: 'bg-sky-50 text-sky-500',
    title: 'E-Ticket dan Invoice',
    body: 'Setelah pembayaran terkonfirmasi, e-ticket QR Code dan invoice PDF resmi langsung dikirim ke email pelanggan.',
  },
];

const steps = [
  {
    icon: MapPin,
    title: 'Pilih lapangan dan jadwal',
    body: 'Buka halaman venue, lihat ketersediaan slot secara langsung, lalu pilih jam yang kamu mau.',
  },
  {
    icon: QrCode,
    title: 'Bayar dengan aman',
    body: 'Selesaikan pembayaran lewat QRIS atau e-wallet. Slot otomatis terkunci selama proses berlangsung.',
  },
  {
    icon: Receipt,
    title: 'Main dan tunjukkan tiket',
    body: 'Terima e-ticket beserta QR Code. Tunjukkan saat tiba di lokasi, tanpa antre di kasir.',
  },
];

const ownerPoints = [
  'Dukungan multi court untuk banyak lapangan sekaligus',
  'Pemesanan walk-in di tempat dengan kunci slot manual',
  'Kalender jadwal interaktif untuk setiap lapangan',
  'Rekap okupansi harian dan grafik pendapatan bersih',
];

export default function Home() {
  const { isAuthenticated, user, logout } = useAuth();

  const getDashboardLink = () => {
    if (!user) return '/';
    if (user.role === 'owner' || user.role === 'staff') {
      const tenant = user.tenants && user.tenants.length > 0 ? user.tenants[0] : null;
      return tenant ? `/admin/${tenant.slug}` : '/onboard';
    }
    return '/senayan-sport'; // Fallback for players to book
  };

  const handleLogout = async () => {
    try {
      await logout();
    } catch {
      // Ignored
    }
  };

  const isStaffOrOwner = user?.role === 'owner' || user?.role === 'staff';

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col font-sans text-slate-900">

      {/* Navbar */}
      <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          <Link to="/" className="flex items-center gap-2.5">
            <span className="grid place-items-center h-9 w-9 rounded-lg bg-emerald-600 text-white">
              <SoccerBall size={22} weight="fill" />
            </span>
            <span className="text-lg font-extrabold tracking-tight text-slate-900">
              LapanginAja
            </span>
          </Link>
          <div className="flex items-center gap-2 sm:gap-3">
            {isAuthenticated && user ? (
              <>
                <span className="hidden sm:inline text-sm text-slate-500">
                  Halo, <span className="font-semibold text-slate-800">{user.name}</span>
                </span>
                <Link to={getDashboardLink()}>
                  <Button className="px-4 py-2 text-sm">
                    {isStaffOrOwner ? 'Dashboard Admin' : 'Pesan Lapangan'}
                  </Button>
                </Link>
                <Button variant="secondary" className="px-4 py-2 text-sm" onClick={handleLogout}>
                  Keluar
                </Button>
              </>
            ) : (
              <>
                <Link to="/login">
                  <Button variant="secondary" className="px-4 py-2">
                    Masuk
                  </Button>
                </Link>
                <Link to="/register">
                  <Button className="px-4 py-2">
                    Daftar
                  </Button>
                </Link>
              </>
            )}
          </div>
        </div>
      </header>

      {/* Hero */}
      <section className="relative overflow-hidden border-b border-slate-200 bg-white">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute -top-24 -right-24 h-80 w-80 rounded-full bg-emerald-100/60 blur-3xl"
        />
        <div
          aria-hidden="true"
          className="pointer-events-none absolute -bottom-32 -left-24 h-80 w-80 rounded-full bg-orange-100/40 blur-3xl"
        />
        <div className="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24 grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
          {/* Left column */}
          <div className="flex flex-col gap-6">
            <span className="inline-flex items-center gap-2 self-start rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
              <Lightning size={14} weight="fill" />
              Booking lapangan tanpa ribet
            </span>
            <h1 className="text-4xl sm:text-5xl font-extrabold leading-[1.1] tracking-tight text-slate-900">
              Pesan lapangan olahraga online, cepat dan pasti dapat
            </h1>
            <p className="text-lg text-slate-600 leading-relaxed max-w-xl">
              Lihat jadwal secara langsung, kunci slot favoritmu, lalu bayar lewat QRIS atau e-wallet.
              Semua beres dari satu halaman, tanpa perlu menunggu balasan admin.
            </p>
            <div className="flex flex-wrap items-center gap-3 mt-1">
              {isAuthenticated && user ? (
                <Link to={getDashboardLink()}>
                  <Button className="px-6 py-3 text-base">
                    {isStaffOrOwner ? 'Masuk ke Dashboard Anda' : 'Mulai Pesan Lapangan'}
                  </Button>
                </Link>
              ) : (
                <Link to="/register?role=owner">
                  <Button className="px-6 py-3 text-base" icon={<Storefront size={20} weight="fill" />}>
                    Daftarkan Lapanganmu
                  </Button>
                </Link>
              )}
              <Link to="/senayan-sport">
                <Button variant="secondary" className="px-6 py-3 text-base" icon={<DeviceMobile size={20} />}>
                  Lihat Demo Venue
                </Button>
              </Link>
            </div>
            <ul className="flex flex-wrap gap-x-6 gap-y-2 mt-2 text-sm text-slate-600">
              <li className="flex items-center gap-2">
                <CheckCircle size={18} weight="fill" className="text-emerald-500" />
                Tanpa biaya pendaftaran
              </li>
              <li className="flex items-center gap-2">
                <CheckCircle size={18} weight="fill" className="text-emerald-500" />
                Konfirmasi instan
              </li>
              <li className="flex items-center gap-2">
                <CheckCircle size={18} weight="fill" className="text-emerald-500" />
                E-ticket via email
              </li>
            </ul>
          </div>

          {/* Right column: schedule preview */}
          <div className="relative lg:pl-6">
            <div className="w-full max-w-md mx-auto lg:ml-auto rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.05),0_12px_32px_rgba(15,23,42,0.08)] p-5 sm:p-6">
              <div className="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div className="flex flex-col gap-1">
                  <span className="font-bold text-slate-900">Lapangan Futsal A</span>
                  <span className="flex items-center gap-1.5 text-xs text-slate-500">
                    <MapPin size={14} weight="fill" className="text-slate-400" />
                    Senayan Sport Center
                  </span>
                </div>
                <span className="flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                  <Clock size={13} weight="fill" />
                  Hari ini
                </span>
              </div>
              <div className="grid grid-cols-3 gap-2 py-4">
                {previewSlots.map((slot) => (
                  <div
                    key={slot.time}
                    className={`flex items-center justify-center rounded-lg border py-2.5 text-sm font-semibold ${slotStyles[slot.state]}`}
                  >
                    {slot.time}
                  </div>
                ))}
              </div>
              <div className="flex items-center justify-between border-t border-slate-100 pt-4">
                <div className="flex flex-col">
                  <span className="text-xs text-slate-500">Slot dipilih 10:00</span>
                  <span className="font-bold text-slate-900">Rp120.000</span>
                </div>
                <span className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                  Bayar sekarang
                </span>
              </div>
            </div>
            <div className="absolute -bottom-5 left-2 sm:left-6 flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-[0_8px_24px_rgba(15,23,42,0.10)]">
              <CheckCircle size={24} weight="fill" className="text-emerald-500" />
              <div className="flex flex-col leading-tight">
                <span className="text-sm font-semibold text-slate-900">Pembayaran berhasil</span>
                <span className="text-xs text-slate-500">E-ticket terkirim ke email</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Payment methods strip */}
      <section className="border-b border-slate-200 bg-white/60">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col sm:flex-row items-center gap-4 sm:gap-6">
          <span className="text-sm font-semibold text-slate-500 whitespace-nowrap">
            Menerima berbagai metode pembayaran
          </span>
          <div className="flex flex-wrap items-center justify-center gap-2">
            {paymentMethods.map((method) => (
              <span
                key={method}
                className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600"
              >
                {method}
              </span>
            ))}
          </div>
        </div>
      </section>

      {/* Features */}
      <section className="py-20 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto w-full">
        <div className="max-w-2xl flex flex-col gap-3 mb-12">
          <span className="text-sm font-semibold uppercase tracking-wide text-emerald-600">
            Kenapa LapanginAja
          </span>
          <h2 className="text-3xl font-extrabold tracking-tight text-slate-900">
            Semua yang dibutuhkan untuk booking lapangan
          </h2>
          <p className="text-slate-600 leading-relaxed">
            Dibuat untuk pemain yang ingin cepat dapat slot dan untuk pemilik venue yang ingin
            kelola pemesanan dengan rapi.
          </p>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {features.map((feature) => {
            const Icon = feature.icon;
            return (
              <div
                key={feature.title}
                className="group flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 transition-shadow duration-200 hover:shadow-[0_1px_2px_rgba(0,0,0,0.05),0_10px_24px_rgba(15,23,42,0.06)]"
              >
                <div className={`grid place-items-center h-12 w-12 rounded-xl ${feature.accent}`}>
                  <Icon size={26} weight="duotone" />
                </div>
                <h3 className="text-lg font-bold text-slate-900">{feature.title}</h3>
                <p className="text-sm text-slate-600 leading-relaxed">{feature.body}</p>
              </div>
            );
          })}
        </div>
      </section>

      {/* How it works */}
      <section className="border-y border-slate-200 bg-white">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
          <div className="max-w-2xl flex flex-col gap-3 mb-12">
            <span className="text-sm font-semibold uppercase tracking-wide text-emerald-600">
              Cara kerja
            </span>
            <h2 className="text-3xl font-extrabold tracking-tight text-slate-900">
              Dari pilih slot sampai main, hanya tiga langkah
            </h2>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {steps.map((step, index) => {
              const Icon = step.icon;
              return (
                <div key={step.title} className="relative flex flex-col gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-6">
                  <div className="flex items-center justify-between">
                    <div className="grid place-items-center h-12 w-12 rounded-xl bg-white border border-slate-200 text-emerald-600">
                      <Icon size={26} weight="duotone" />
                    </div>
                    <span className="text-4xl font-extrabold text-slate-200">{index + 1}</span>
                  </div>
                  <h3 className="text-lg font-bold text-slate-900">{step.title}</h3>
                  <p className="text-sm text-slate-600 leading-relaxed">{step.body}</p>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      {/* Owner benefits */}
      <section className="py-20 px-4 sm:px-6 lg:px-8">
        <div className="max-w-6xl mx-auto grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
          <div className="flex flex-col gap-5">
            <span className="text-sm font-semibold uppercase tracking-wide text-emerald-600">
              Untuk pemilik venue
            </span>
            <h2 className="text-3xl font-extrabold tracking-tight text-slate-900">
              Kelola venue olahraga Anda secara profesional
            </h2>
            <p className="text-slate-600 leading-relaxed">
              LapanginAja menyediakan dashboard multi-tenant lengkap dengan nama brand Anda sendiri.
              Atur jam operasional, pantau okupansi, dan lihat performa keuangan harian dalam satu tempat.
            </p>
            <ul className="flex flex-col gap-3 mt-1">
              {ownerPoints.map((point) => (
                <li key={point} className="flex items-start gap-3 text-sm text-slate-700">
                  <CheckCircle size={20} weight="fill" className="text-emerald-500 shrink-0 mt-0.5" />
                  {point}
                </li>
              ))}
            </ul>
            <div className="mt-3">
              {isAuthenticated && user ? (
                <Link to={getDashboardLink()}>
                  <Button icon={<House size={18} weight="fill" />}>
                    {isStaffOrOwner ? 'Buka Dashboard Kelola' : 'Mulai Pesan'}
                  </Button>
                </Link>
              ) : (
                <Link to="/register?role=owner">
                  <Button icon={<House size={18} weight="fill" />}>
                    Daftar Sebagai Owner
                  </Button>
                </Link>
              )}
            </div>
          </div>

          {/* Dashboard preview card */}
          <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_1px_2px_rgba(0,0,0,0.05),0_12px_32px_rgba(15,23,42,0.06)]">
            <div className="flex items-center gap-2.5 border-b border-slate-100 pb-4">
              <span className="grid place-items-center h-9 w-9 rounded-lg bg-emerald-600 text-white">
                <SoccerBall size={20} weight="fill" />
              </span>
              <span className="font-bold text-slate-800">Dashboard Venue</span>
            </div>
            <div className="grid grid-cols-2 gap-3 py-4">
              <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <span className="text-xs text-slate-500">Okupansi hari ini</span>
                <div className="mt-1 flex items-end gap-2">
                  <span className="text-2xl font-extrabold text-slate-900">82%</span>
                  <span className="flex items-center gap-1 text-xs font-semibold text-emerald-600">
                    <ChartLineUp size={14} weight="bold" />
                    naik
                  </span>
                </div>
              </div>
              <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <span className="text-xs text-slate-500">Pendapatan hari ini</span>
                <div className="mt-1">
                  <span className="text-2xl font-extrabold text-slate-900">Rp3,4jt</span>
                </div>
              </div>
            </div>
            <div className="flex flex-col gap-2.5">
              {['Lapangan Futsal A', 'Lapangan Badminton 1', 'Lapangan Basket'].map((court, i) => (
                <div key={court} className="flex items-center justify-between rounded-lg border border-slate-100 bg-white px-3 py-2.5">
                  <span className="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <CalendarCheck size={16} weight="duotone" className="text-emerald-600" />
                    {court}
                  </span>
                  <span className={`text-xs font-semibold ${i === 1 ? 'text-orange-500' : 'text-emerald-600'}`}>
                    {i === 1 ? '3 slot tersisa' : 'Tersedia'}
                  </span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="mt-auto bg-slate-900 text-slate-400">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 flex flex-col sm:flex-row justify-between items-center gap-6">
          <div className="flex items-center gap-2.5 text-white">
            <span className="grid place-items-center h-8 w-8 rounded-lg bg-emerald-600">
              <SoccerBall size={18} weight="fill" />
            </span>
            <span className="font-bold tracking-tight">LapanginAja</span>
          </div>
          <nav className="flex items-center gap-6 text-sm">
            <Link to="/senayan-sport" className="hover:text-white transition-colors">Demo Venue</Link>
            <Link to="/login" className="hover:text-white transition-colors">Masuk</Link>
            <Link to="/register" className="hover:text-white transition-colors">Daftar</Link>
          </nav>
          <div className="text-xs text-slate-500">
            Copyright 2026 LapanginAja. Seluruh hak cipta dilindungi.
          </div>
        </div>
      </footer>

    </div>
  );
}
