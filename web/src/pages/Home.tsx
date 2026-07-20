import { Link } from 'react-router-dom';
import { Button } from '../components/ui/Button';
import { useAuth } from '../context/AuthContext';
import {
  ArrowRight,
  Clock,
  CreditCard,
  DeviceMobile,
  MapPin,
  QrCode,
  Receipt,
} from '@phosphor-icons/react';
import logoMark from '../assets/lapanginaja-mark.png';

const previewSlots = [
  { time: '08:00', state: 'available' },
  { time: '09:00', state: 'selected' },
  { time: '10:00', state: 'booked' },
  { time: '11:00', state: 'available' },
  { time: '12:00', state: 'booked' },
  { time: '13:00', state: 'available' },
];

const features = [
  { no: '01', icon: DeviceMobile, title: 'Link Bio Mulus', body: 'Pelanggan buka link, cek jadwal, lalu pilih jam sendiri. Nggak perlu chat admin dulu.' },
  { no: '02', icon: CreditCard, title: 'Duit Masuk Otomatis', body: 'QRIS dan pembayaran digital langsung diverifikasi. Slot baru terkunci kalau bayarannya beres.' },
  { no: '03', icon: Clock, title: 'Standby 24 Jam', body: 'Booking tetap jalan waktu lu tidur. Sistem kerja terus tanpa minta lembur atau kopi.' },
];

const steps = [
  { icon: MapPin, title: 'Pilih venue', body: 'Cek lapangan dan jam kosong langsung dari satu halaman.' },
  { icon: QrCode, title: 'Bayar sendiri', body: 'Pilih QRIS atau metode digital lain. Nggak pake kirim bukti transfer.' },
  { icon: Receipt, title: 'Datang dan main', body: 'E-ticket langsung masuk. Tinggal datang, tunjukkan tiket, lalu gas main.' },
];

const testimonials = [
  { quote: 'Dulu satu admin bisa habis setengah hari cuma buat bales chat. Sekarang pelanggan beresin booking sendiri.', name: 'Raka Pratama', role: 'Owner Arena 78, Bandung' },
  { quote: 'Jadwal bentrok turun drastis. Yang paling enak, laporan harian nggak perlu dirakit manual lagi.', name: 'Nadia Putri', role: 'Manager Smash Hall, Surabaya' },
  { quote: 'Booking jam malam tetap masuk waktu tim udah pulang. Link venue ini beneran jadi kasir kedua.', name: 'Fajar Aditya', role: 'Owner Kickspace, Makassar' },
];

function Brand() {
  return (
    <span className="flex items-center gap-3">
      <img src={logoMark} alt="" className="h-9 w-9 object-contain" />
      <span className="font-display text-xl font-bold tracking-[-0.06em]">LapanginAja</span>
    </span>
  );
}

export default function Home() {
  const { isAuthenticated, user, logout } = useAuth();

  const getDashboardLink = () => {
    if (!user) return '/';
    if (user.role === 'owner' || user.role === 'staff') {
      const tenant = user.tenants && user.tenants.length > 0 ? user.tenants[0] : null;
      return tenant ? `/admin/${tenant.slug}` : '/onboard';
    }
    return '/senayan-sport';
  };

  const handleLogout = async () => {
    try { await logout(); } catch { /* Ignored */ }
  };

  const isStaffOrOwner = user?.role === 'owner' || user?.role === 'staff';

  return (
    <div className="min-h-screen bg-[#FDFBF7] text-[#064E3B]">
      <div className="editorial-shell">
        <header className="sticky top-0 z-40 grid grid-cols-[1fr_auto] border-b border-[#064E3B] bg-[#FDFBF7] lg:grid-cols-[310px_1fr_auto]">
          <Link to="/" className="flex min-h-18 items-center px-5 lg:border-r lg:border-[#064E3B] lg:px-8"><Brand /></Link>
          <nav className="hidden items-stretch lg:flex">
            <a href="#fitur" className="flex items-center border-r border-[#064E3B] px-8 font-semibold uppercase tracking-[0.08em] hover:bg-[#10B981]">Fitur</a>
            <a href="#cara-kerja" className="flex items-center border-r border-[#064E3B] px-8 font-semibold uppercase tracking-[0.08em] hover:bg-[#10B981]">Cara Kerja</a>
            <a href="#cerita-owner" className="flex items-center border-r border-[#064E3B] px-8 font-semibold uppercase tracking-[0.08em] hover:bg-[#10B981]">Cerita Owner</a>
          </nav>
          <div className="flex items-stretch">
            {isAuthenticated && user ? (
              <>
                <Link to={getDashboardLink()} className="flex items-center border-l border-[#064E3B] px-4 text-sm font-bold uppercase hover:bg-[#10B981] sm:px-6">{isStaffOrOwner ? 'Dashboard' : 'Cari Lapangan'}</Link>
                <button onClick={handleLogout} className="border-l border-[#064E3B] bg-[#064E3B] px-4 text-sm font-bold uppercase text-[#FDFBF7] hover:bg-[#10B981] hover:text-[#064E3B] sm:px-6">Keluar</button>
              </>
            ) : (
              <>
                <Link to="/login" className="hidden items-center border-l border-[#064E3B] px-6 text-sm font-bold uppercase hover:bg-[#10B981] sm:flex">Masuk</Link>
                <Link to="/register" className="flex items-center border-l border-[#064E3B] bg-[#064E3B] px-5 text-sm font-bold uppercase text-[#FDFBF7] hover:bg-[#10B981] hover:text-[#064E3B] sm:px-7">Daftar Gratis</Link>
              </>
            )}
          </div>
        </header>

        <main>
          <section className="grid border-b border-[#064E3B] lg:grid-cols-[1.15fr_.85fr]">
            <div className="flex flex-col justify-center border-b border-[#064E3B] p-7 sm:p-12 lg:min-h-[720px] lg:border-b-0 lg:border-r lg:p-16 xl:p-20">
              <span className="mb-8 w-max border border-[#064E3B] bg-[#10B981] px-4 py-2 text-xs font-bold uppercase tracking-[0.16em]">#LapanginAjaDulu</span>
              <h1 className="font-display max-w-4xl text-[clamp(3rem,6vw,6.7rem)] font-bold uppercase leading-[0.84] tracking-[-0.075em]">
                Lapangin Aja<br /><span className="text-[#10B981]">Kapan Aja</span><br />Di Mana Aja
              </h1>
              <p className="mt-9 max-w-2xl text-lg leading-relaxed sm:text-2xl">
                Mau main tinggal pilih jam. Mau jual slot tinggal bagiin link. <strong>Jadwal, bayar, dan tiket beres tanpa chat “min, kosong?”</strong>
              </p>
              <div className="mt-10 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                <Link to={isAuthenticated ? getDashboardLink() : '/register?role=owner'}>
                  <Button className="px-7 py-4 text-base" icon={<ArrowRight size={20} weight="bold" />}>{isAuthenticated ? 'BUKA DASHBOARD' : 'BUAT LINK SEKARANG'}</Button>
                </Link>
                <Link to="/senayan-sport">
                  <Button variant="secondary" className="px-7 py-4 text-base" icon={<DeviceMobile size={20} weight="bold" />}>DEMO VENUE</Button>
                </Link>
              </div>
            </div>

            <div className="relative flex min-h-[620px] items-center justify-center overflow-hidden bg-[#064E3B] p-7 sm:p-14">
              <div aria-hidden="true" className="absolute inset-x-0 top-[18%] h-px bg-[#10B981]/40" />
              <div aria-hidden="true" className="absolute bottom-[18%] inset-x-0 h-px bg-[#10B981]/40" />
              <div aria-hidden="true" className="absolute inset-y-0 left-[22%] w-px bg-[#10B981]/40" />
              <div className="relative w-full max-w-md border-2 border-[#FDFBF7] bg-[#FDFBF7] hard-shadow">
                <div className="flex items-center justify-between border-b-2 border-[#064E3B] p-5">
                  <div><p className="font-display text-lg font-bold uppercase">Booking Lapangan</p><p className="text-xs font-semibold uppercase tracking-[0.12em] opacity-65">Senayan Sport Center</p></div>
                  <span className="h-4 w-4 bg-[#10B981]" />
                </div>
                <div className="p-5">
                  <div className="mb-4 flex items-center justify-between text-xs font-bold uppercase tracking-[0.12em]"><span>Pilih Jam</span><span>Hari Ini</span></div>
                  <div className="grid grid-cols-3 border-l border-t border-[#064E3B]">
                    {previewSlots.map((slot) => <div key={slot.time} className={`border-b border-r border-[#064E3B] p-4 text-center font-bold ${slot.state === 'selected' ? 'bg-[#064E3B] text-[#FDFBF7]' : slot.state === 'booked' ? 'bg-[#064E3B]/10 opacity-40' : 'bg-[#FDFBF7]'}`}>{slot.time}</div>)}
                  </div>
                </div>
                <div className="border-t-2 border-[#064E3B] bg-[#10B981] p-5">
                  <div className="mb-5 flex items-end justify-between"><div><span className="text-xs font-bold uppercase tracking-[0.12em]">Total</span><p className="font-display text-3xl font-bold">Rp150.000</p></div><span className="border-2 border-[#064E3B] bg-[#FDFBF7] px-3 py-1 text-xs font-bold">QRIS</span></div>
                  <div className="bg-[#064E3B] p-4 text-center font-display font-bold uppercase text-[#FDFBF7]">Bayar dan kunci slot</div>
                </div>
              </div>
            </div>
          </section>

          <div className="overflow-hidden border-b border-[#064E3B] bg-[#10B981] py-4">
            <div className="editorial-ticker flex w-max whitespace-nowrap font-display text-lg font-bold uppercase tracking-[0.12em]">
              {[0, 1].map((copy) => <div key={copy} className="flex"><span className="mx-8">Mesin uang otomatis</span><span className="mx-8">Bye bye jadwal bentrok</span><span className="mx-8">QRIS verifikasi instan</span><span className="mx-8">Buka 24 jam penuh</span></div>)}
            </div>
          </div>

          <section id="fitur" className="border-b border-[#064E3B]">
            <div className="grid border-b border-[#064E3B] lg:grid-cols-[.45fr_1fr]">
              <div className="border-b border-[#064E3B] p-8 lg:border-b-0 lg:border-r lg:p-14"><span className="text-xs font-bold uppercase tracking-[0.18em]">Fitur inti / 2026</span></div>
              <div className="p-8 sm:p-14"><h2 className="font-display text-4xl font-bold uppercase leading-[.95] tracking-[-0.055em] sm:text-6xl">Platform waras buat owner yang capek ribet.</h2></div>
            </div>
            <div className="grid md:grid-cols-3">
              {features.map((feature, index) => { const Icon = feature.icon; return <article key={feature.title} className={`group min-h-[390px] p-8 sm:p-10 ${index < 2 ? 'border-b border-[#064E3B] md:border-b-0 md:border-r' : ''} hover:bg-[#10B981]`}>
                <div className="flex items-start justify-between"><Icon size={44} weight="thin" /><span className="font-display text-5xl font-bold opacity-20">{feature.no}</span></div>
                <h3 className="mt-20 font-display text-2xl font-bold uppercase tracking-[-0.04em]">{feature.title}</h3><p className="mt-5 text-lg leading-relaxed">{feature.body}</p>
              </article>; })}
            </div>
          </section>

          <section id="cara-kerja" className="grid border-b border-[#064E3B] lg:grid-cols-[.8fr_1.2fr]">
            <div className="flex flex-col justify-between border-b border-[#064E3B] bg-[#064E3B] p-8 text-[#FDFBF7] lg:min-h-[650px] lg:border-b-0 lg:border-r lg:p-14">
              <span className="text-xs font-bold uppercase tracking-[0.18em] text-[#10B981]">Cara kerja</span>
              <div><h2 className="font-display text-5xl font-bold uppercase leading-[.9] tracking-[-0.06em] sm:text-7xl">Tiga langkah. Nggak pake muter.</h2><p className="mt-8 max-w-lg text-lg leading-relaxed text-[#FDFBF7]/75">Dari pilih lapangan sampai tiket masuk email, semuanya lurus dan gampang dipahami.</p></div>
            </div>
            <div>
              {steps.map((step, index) => { const Icon = step.icon; return <article key={step.title} className={`grid gap-7 p-8 sm:grid-cols-[80px_1fr] sm:p-12 ${index < steps.length - 1 ? 'border-b border-[#064E3B]' : ''}`}><div className="flex items-start justify-between sm:block"><span className="font-display text-4xl font-bold">0{index + 1}</span><Icon className="sm:mt-10" size={34} weight="thin" /></div><div><h3 className="font-display text-3xl font-bold uppercase tracking-[-0.04em]">{step.title}</h3><p className="mt-4 max-w-xl text-lg leading-relaxed">{step.body}</p></div></article>; })}
            </div>
          </section>

          <section id="cerita-owner" className="border-b border-[#064E3B]">
            <div className="grid border-b border-[#064E3B] p-8 sm:p-14 lg:grid-cols-[1fr_.45fr] lg:items-end"><h2 className="font-display text-5xl font-bold uppercase leading-[.9] tracking-[-0.06em] sm:text-7xl">Owner ngomong apa adanya.</h2><p className="mt-8 text-lg leading-relaxed lg:mt-0">Bukan janji manis. Ini perubahan operasional yang kerasa dari hari pertama.</p></div>
            <div className="grid md:grid-cols-3">{testimonials.map((item, index) => <blockquote key={item.name} className={`flex min-h-[360px] flex-col justify-between p-8 sm:p-10 ${index < 2 ? 'border-b border-[#064E3B] md:border-b-0 md:border-r' : ''} ${index === 1 ? 'bg-[#10B981]' : ''}`}><p className="font-display text-2xl font-medium leading-snug">“{item.quote}”</p><footer className="mt-12 border-t border-[#064E3B] pt-5"><strong className="block uppercase">{item.name}</strong><span className="text-sm opacity-70">{item.role}</span></footer></blockquote>)}</div>
          </section>

          <section className="grid border-b border-[#064E3B] bg-[#10B981] lg:grid-cols-[1fr_auto]">
            <div className="p-8 sm:p-14 lg:p-20"><span className="text-xs font-bold uppercase tracking-[0.18em]">Nggak pake nunggu besok</span><h2 className="mt-8 font-display text-[clamp(3.4rem,8vw,8rem)] font-bold uppercase leading-[.82] tracking-[-0.075em]">Hajar daftar sekarang.</h2><p className="mt-8 max-w-2xl text-xl font-medium leading-relaxed">Setup venue cuma butuh waktu setara seduh mie instan. Hari ini daftar, hari ini juga sistem lu mulai kerja.</p></div>
            <div className="flex items-center border-t border-[#064E3B] p-8 lg:w-[360px] lg:border-l lg:border-t-0 lg:p-12"><Link className="w-full" to={isAuthenticated ? getDashboardLink() : '/register?role=owner'}><Button className="min-h-28 w-full px-8 text-lg" icon={<ArrowRight size={26} weight="bold" />}>HAJAR DAFTAR</Button></Link></div>
          </section>
        </main>

        <footer className="grid bg-[#FDFBF7] lg:grid-cols-[1fr_auto]">
          <div className="p-8 sm:p-12"><Brand /><p className="mt-7 max-w-md text-sm leading-relaxed opacity-70">Sistem booking lapangan buat owner yang mau operasional rapi dan pelanggan yang nggak suka nunggu.</p></div>
          <div className="grid grid-cols-3 border-t border-[#064E3B] lg:border-l lg:border-t-0"><Link to="/senayan-sport" className="flex min-h-24 items-center justify-center border-r border-[#064E3B] px-5 text-sm font-bold uppercase hover:bg-[#10B981]">Demo Venue</Link><Link to="/login" className="flex min-h-24 items-center justify-center border-r border-[#064E3B] px-5 text-sm font-bold uppercase hover:bg-[#10B981]">Masuk</Link><Link to="/register" className="flex min-h-24 items-center justify-center px-5 text-sm font-bold uppercase hover:bg-[#10B981]">Daftar</Link></div>
        </footer>
      </div>
    </div>
  );
}
