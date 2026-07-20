import { useState, useEffect } from 'react';
import { Link, Outlet, useLocation, useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { apiClient } from '../../api/client';
import { useToast } from '../../context/ToastContext';
import {
  House,
  Calendar,
  ListBullets,
  UserCheck,
  TennisBall,
  Gear,
  SignOut,
  List,
  X,
  Users,
} from '@phosphor-icons/react';

export default function AdminLayout() {
  const { slug } = useParams<{ slug: string }>();
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const { addToast } = useToast();

  const [tenantName, setTenantName] = useState<string>('Dashboard');
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  useEffect(() => {
    const fetchTenantName = async () => {
      try {
        const response = await apiClient.get(`/public/${slug}`);
        setTenantName(response.data.tenant.name);
      } catch {
        // Fallback
      }
    };
    if (slug) {
      fetchTenantName();
    }
  }, [slug]);

  useEffect(() => {
    if (user && slug && user.role !== 'super_admin') {
      const isMember = user.tenants?.some((t) => t.slug === slug);
      if (!isMember) {
        addToast('Anda tidak memiliki akses ke venue ini.', 'error');
        navigate('/');
      }
    }
  }, [user, slug, navigate, addToast]);

  const baseItems = [
    { label: 'Ringkasan', path: `/admin/${slug}`, icon: House },
    { label: 'Jadwal Kalender', path: `/admin/${slug}/calendar`, icon: Calendar },
    { label: 'Daftar Booking', path: `/admin/${slug}/bookings`, icon: ListBullets },
    { label: 'Booking Walk-In', path: `/admin/${slug}/walk-in`, icon: UserCheck },
  ];

  const isOwner = user?.role === 'owner' || user?.role === 'super_admin';

  const menuItems = [
    ...baseItems,
    ...(isOwner ? [
      { label: 'Kelola Lapangan', path: `/admin/${slug}/courts`, icon: TennisBall },
      { label: 'Kelola Staff', path: `/admin/${slug}/staff`, icon: Users },
      { label: 'Pengaturan Venue', path: `/admin/${slug}/settings`, icon: Gear },
    ] : []),
  ];

  const handleLogout = async () => {
    try {
      await logout();
      addToast('Berhasil keluar.', 'success');
      navigate('/login');
    } catch {
      addToast('Gagal logout.', 'error');
    }
  };

  const isActive = (path: string) => {
    return location.pathname === path;
  };

  return (
    <div className="flex h-screen bg-[#FDFBF7] overflow-hidden font-sans">
      {/* Sidebar for Desktop */}
      <aside className="hidden md:flex flex-col w-64 bg-[#FDFBF7] border-r border-[#064E3B] shrink-0">
        <div className="h-16 flex items-center px-6 border-b border-[#064E3B] bg-[#FDFBF7]/50">
          <span className="text-sm font-black text-[#064E3B] tracking-wide uppercase truncate">
            {tenantName}
          </span>
        </div>

        <nav className="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
          {menuItems.map((item) => {
            const Icon = item.icon;
            const active = isActive(item.path);

            return (
              <Link
                key={item.label}
                to={item.path}
                className={`flex items-center gap-3 px-4 py-3 rounded-none text-sm font-semibold transition-all duration-200 ${
                  active
                    ? 'bg-[#10B981] text-white shadow-[4px_4px_0_#064E3B]'
                    : 'text-[#064E3B]/80 hover:bg-[#FDFBF7] hover:text-[#064E3B]'
                }`}
              >
                <Icon size={20} weight={active ? 'bold' : 'regular'} />
                <span>{item.label}</span>
              </Link>
            );
          })}
        </nav>

        <div className="p-4 border-t border-[#064E3B] flex flex-col gap-2.5">
          <div className="px-4 py-1.5 flex flex-col">
            <span className="text-xs font-bold text-[#064E3B]">{user?.name}</span>
            <span className="text-xxs text-[#064E3B]/45 font-medium capitalize mt-0.5">
              {user?.role}
            </span>
          </div>
          <button
            onClick={handleLogout}
            className="flex items-center gap-3 px-4 py-3 rounded-none text-sm font-semibold text-rose-600 hover:bg-rose-50 hover:text-rose-700 transition-all duration-200 text-left w-full cursor-pointer focus:outline-none"
          >
            <SignOut size={20} />
            <span>Keluar</span>
          </button>
        </div>
      </aside>

      {/* Mobile Sidebar (Drawer) */}
      {isMobileMenuOpen && (
        <div className="fixed inset-0 z-40 md:hidden flex">
          {/* Backdrop */}
          <div
            className="fixed inset-0 bg-[#064E3B]/60  transition-opacity"
            onClick={() => setIsMobileMenuOpen(false)}
          />

          {/* Menu Panel */}
          <div className="relative flex flex-col w-64 max-w-xs bg-[#FDFBF7] h-full border-r border-[#064E3B] z-10 animate-slide-in">
            <div className="h-16 flex items-center justify-between px-6 border-b border-[#064E3B] bg-[#FDFBF7]/50">
              <span className="text-sm font-black text-[#064E3B] tracking-wide uppercase truncate">
                {tenantName}
              </span>
              <button
                onClick={() => setIsMobileMenuOpen(false)}
                className="text-[#064E3B]/65 hover:text-[#064E3B] focus:outline-none"
              >
                <X size={20} />
              </button>
            </div>

            <nav className="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
              {menuItems.map((item) => {
                const Icon = item.icon;
                const active = isActive(item.path);

                return (
                  <Link
                    key={item.label}
                    to={item.path}
                    onClick={() => setIsMobileMenuOpen(false)}
                    className={`flex items-center gap-3 px-4 py-3 rounded-none text-sm font-semibold transition-all duration-200 ${
                      active
                        ? 'bg-[#10B981] text-white shadow-[4px_4px_0_#064E3B]'
                        : 'text-[#064E3B]/80 hover:bg-[#FDFBF7] hover:text-[#064E3B]'
                    }`}
                  >
                    <Icon size={20} weight={active ? 'bold' : 'regular'} />
                    <span>{item.label}</span>
                  </Link>
                );
              })}
            </nav>

            <div className="p-4 border-t border-[#064E3B] flex flex-col gap-2.5">
              <div className="px-4 py-1.5 flex flex-col">
                <span className="text-xs font-bold text-[#064E3B]">{user?.name}</span>
                <span className="text-xxs text-[#064E3B]/45 capitalize mt-0.5">
                  {user?.role}
                </span>
              </div>
              <button
                onClick={handleLogout}
                className="flex items-center gap-3 px-4 py-3 rounded-none text-sm font-semibold text-rose-600 hover:bg-rose-50 hover:text-rose-700 transition-all duration-200 text-left w-full cursor-pointer focus:outline-none"
              >
                <SignOut size={20} />
                <span>Keluar</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Main Content Area */}
      <div className="flex flex-col flex-1 overflow-hidden">
        {/* Top Header */}
        <header className="h-16 flex items-center justify-between px-6 bg-[#FDFBF7] border-b border-[#064E3B] shrink-0">
          <button
            onClick={() => setIsMobileMenuOpen(true)}
            className="md:hidden text-[#064E3B]/80 hover:text-[#064E3B] focus:outline-none"
          >
            <List size={24} />
          </button>
          <div className="text-sm font-semibold text-[#064E3B] capitalize md:ml-0 ml-4">
            Kelola Operasional Venue
          </div>
        </header>

        {/* Content Body */}
        <main className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
