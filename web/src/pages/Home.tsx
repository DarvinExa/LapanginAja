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
} from '@phosphor-icons/react';

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

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col font-sans">
      
      {/* Navbar Header */}
      <header className="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <SoccerBall size={28} className="text-emerald-600" weight="fill" />
            <span className="text-lg font-black text-slate-800 tracking-tight uppercase">
              LapanginAja
            </span>
          </div>
          <div className="flex items-center gap-3">
            {isAuthenticated && user ? (
              <>
                <span className="hidden sm:inline text-xs font-bold text-slate-600">
                  Halo, <span className="text-slate-800">{user.name}</span>
                </span>
                <Link to={getDashboardLink()}>
                  <Button className="px-4 py-2 text-xs">
                    {user.role === 'owner' || user.role === 'staff' ? 'Dashboard Admin' : 'Pesan Lapangan'}
                  </Button>
                </Link>
                <Button variant="secondary" className="px-4 py-2 text-xs" onClick={handleLogout}>
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

      {/* Hero Section */}
      <section className="bg-white border-b border-slate-200 py-20 px-4 sm:px-6 lg:px-8 text-center flex flex-col items-center justify-center">
        <div className="max-w-3xl mx-auto flex flex-col gap-6">
          <h1 className="text-4xl sm:text-5xl font-black text-slate-900 leading-tight">
            Booking Lapangan Olahraga Online Mudah & Cepat
          </h1>
          <p className="text-slate-600 text-base sm:text-lg max-w-2xl mx-auto leading-relaxed">
            Platform SaaS multi-tenant terbaik untuk memesan slot lapangan olahraga secara langsung dengan pembayaran instan QRIS dan e-wallet.
          </p>
          <div className="flex flex-wrap items-center justify-center gap-3 mt-4">
            {isAuthenticated && user ? (
              <Link to={getDashboardLink()}>
                <Button className="px-6 py-3 text-base">
                  {user.role === 'owner' || user.role === 'staff' ? 'Masuk ke Dashboard Anda' : 'Mulai Pesan Lapangan'}
                </Button>
              </Link>
            ) : (
              <Link to="/register?role=owner">
                <Button className="px-6 py-3 text-base">
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
        </div>
      </section>

      {/* Features Grid Section */}
      <section className="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">
        <h2 className="text-center text-2xl sm:text-3xl font-black text-slate-800 mb-12">
          Kenapa Memilih LapanginAja?
        </h2>
        
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {/* Feature 1 */}
          <div className="bg-white border border-slate-200 p-6 rounded-xl shadow-sm flex flex-col gap-4">
            <div className="p-3 bg-emerald-50 text-emerald-600 rounded-lg w-fit">
              <CalendarCheck size={24} weight="duotone" />
            </div>
            <h3 className="font-bold text-slate-800 text-lg">Pemesanan Real-Time</h3>
            <p className="text-slate-600 text-sm leading-relaxed">
              Pilih slot waktu lapangan olahraga yang Anda sukai dan langsung kunci slot seketika tanpa perlu chat admin manual.
            </p>
          </div>

          {/* Feature 2 */}
          <div className="bg-white border border-slate-200 p-6 rounded-xl shadow-sm flex flex-col gap-4">
            <div className="p-3 bg-orange-50 text-orange-500 rounded-lg w-fit">
              <CreditCard size={24} weight="duotone" />
            </div>
            <h3 className="font-bold text-slate-800 text-lg">Pembayaran Otomatis</h3>
            <p className="text-slate-600 text-sm leading-relaxed">
              Integrasi pembayaran instan menggunakan Midtrans Snap sandbox. Bayar lunas pakai QRIS, gopay, ShopeePay, atau transfer bank.
            </p>
          </div>

          {/* Feature 3 */}
          <div className="bg-white border border-slate-200 p-6 rounded-xl shadow-sm flex flex-col gap-4">
            <div className="p-3 bg-sky-50 text-sky-500 rounded-lg w-fit">
              <ShieldCheck size={24} weight="duotone" />
            </div>
            <h3 className="font-bold text-slate-800 text-lg">E-Ticket & Invoice</h3>
            <p className="text-slate-600 text-sm leading-relaxed">
              Setelah pembayaran berhasil terkonfirmasi, e-ticket QR Code dan invoice PDF resmi dikirimkan langsung ke nomor WhatsApp dan email Anda.
            </p>
          </div>
        </div>
      </section>

      {/* Owner Benefits Panel */}
      <section className="bg-white border-t border-b border-slate-200 py-16 px-4 sm:px-6 lg:px-8">
        <div className="max-w-5xl mx-auto flex flex-col md:flex-row items-center gap-10">
          <div className="flex-1 flex flex-col gap-4">
            <h2 className="text-2xl sm:text-3xl font-black text-slate-800">
              Kelola Venue Olahraga Anda Secara Profesional
            </h2>
            <p className="text-slate-600 text-sm leading-relaxed">
              LapanginAja menyediakan fitur SaaS multi-tenant lengkap bagi pemilik bisnis lapangan olahraga. Anda dapat membuat dashboard khusus dengan nama brand Anda sendiri, mengelola jam operasional, melacak rekap okupansi serta performa keuangan harian.
            </p>
             <div className="mt-2">
              {isAuthenticated && user ? (
                <Link to={getDashboardLink()}>
                  <Button icon={<House size={18} />}>
                    {user.role === 'owner' || user.role === 'staff' ? 'Buka Dashboard Kelola' : 'Mulai Pesan'}
                  </Button>
                </Link>
              ) : (
                <Link to="/register?role=owner">
                  <Button icon={<House size={18} />}>
                    Daftar Sebagai Owner
                  </Button>
                </Link>
              )}
            </div>
          </div>
          <div className="flex-1 w-full bg-slate-50 border border-slate-200 rounded-xl p-8 flex flex-col gap-4 shadow-xs">
            <div className="flex items-center gap-3 border-b border-slate-200/60 pb-3">
              <SoccerBall size={24} className="text-emerald-600" />
              <span className="font-bold text-slate-700">Fitur Dashboard Venue:</span>
            </div>
            <ul className="text-xs text-slate-600 flex flex-col gap-2.5">
              <li className="flex items-center gap-2">
                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0" />
                Dukungan Multi-Court (banyak lapangan sekaligus)
              </li>
              <li className="flex items-center gap-2">
                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0" />
                Pemesanan Offline Walk-In di tempat (kunci slot manual)
              </li>
              <li className="flex items-center gap-2">
                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0" />
                Kalender jadwal interaktif per lapangan
              </li>
              <li className="flex items-center gap-2">
                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 shrink-0" />
                Statistik performa okupansi harian & grafik pendapatan bersih
              </li>
            </ul>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="mt-auto py-8 bg-slate-900 text-slate-400 text-center text-xs">
        <div className="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row justify-between items-center gap-4">
          <div className="flex items-center gap-2 text-white">
            <SoccerBall size={20} className="text-emerald-500" weight="fill" />
            <span className="font-bold tracking-wider">LAPANGINAJA</span>
          </div>
          <div>
            &copy; 2026 LapanginAja. Semua hak cipta dilindungi undang-undang.
          </div>
        </div>
      </footer>

    </div>
  );
}
