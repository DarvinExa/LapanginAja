import { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { apiClient } from '../../api/client';
import { Skeleton } from '../../components/ui/Skeleton';
import { Input } from '../../components/ui/Input';
import { Button } from '../../components/ui/Button';
import { useToast } from '../../context/ToastContext';
import { UserCheck, Trash, Plus, Users, X } from '@phosphor-icons/react';

interface StaffUser {
  id: number;
  name: string;
  email: string;
  phone: string;
  role: string;
  created_at: string;
}

export default function StaffManagement() {
  const { slug } = useParams<{ slug: string }>();
  const { addToast } = useToast();

  const [tenantId, setTenantId] = useState<number | null>(null);
  const [staffList, setStaffList] = useState<StaffUser[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [isDeleting, setIsDeleting] = useState<number | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);

  // Form states
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});

  // 1. Fetch Tenant ID
  useEffect(() => {
    const fetchTenant = async () => {
      try {
        const response = await apiClient.get(`/public/${slug}`);
        setTenantId(response.data.tenant.id);
      } catch {
        addToast('Gagal memuat detail venue.', 'error');
      }
    };
    if (slug) {
      fetchTenant();
    }
  }, [slug, addToast]);

  // 2. Fetch Staff Members
  const fetchStaff = useCallback(async () => {
    if (!tenantId) return;
    setIsLoading(true);
    try {
      const response = await apiClient.get(`/tenants/${tenantId}/staff`);
      setStaffList(response.data.staff);
    } catch {
      addToast('Gagal memuat daftar staff.', 'error');
    } finally {
      setIsLoading(false);
    }
  }, [tenantId, addToast]);

  useEffect(() => {
    fetchStaff();
  }, [fetchStaff]);

  const handleCreateStaff = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!tenantId) return;
    setErrors({});
    setIsSaving(true);

    try {
      await apiClient.post(`/tenants/${tenantId}/staff`, {
        name,
        email,
        phone,
        password,
      });

      addToast('Akun staff berhasil dibuat.', 'success');
      setIsModalOpen(false);
      // Reset form
      setName('');
      setEmail('');
      setPhone('');
      setPassword('');
      fetchStaff();
    } catch (err: any) {
      if (err.response?.data?.errors) {
        const valErrors: Record<string, string> = {};
        Object.keys(err.response.data.errors).forEach((key) => {
          valErrors[key] = err.response.data.errors[key][0];
        });
        setErrors(valErrors);
      } else {
        addToast(
          err.response?.data?.message || 'Gagal membuat akun staff.',
          'error'
        );
      }
    } finally {
      setIsSaving(false);
    }
  };

  const handleDeleteStaff = async (userId: number) => {
    if (!tenantId) return;
    if (!confirm('Apakah Anda yakin ingin menonaktifkan akun staff ini?')) return;
    setIsDeleting(userId);

    try {
      await apiClient.delete(`/tenants/${tenantId}/staff/${userId}`);
      addToast('Staff berhasil dinonaktifkan.', 'success');
      fetchStaff();
    } catch {
      addToast('Gagal menonaktifkan staff.', 'error');
    } finally {
      setIsDeleting(null);
    }
  };

  return (
    <div className="flex flex-col gap-6">
      {/* Title Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Users size={24} className="text-emerald-600" />
          <div>
            <h2 className="text-base font-bold text-slate-800">Kelola Akun Staff (CS)</h2>
            <p className="text-xxs text-slate-400 font-medium">Buat akun untuk komputer/gadget customer service karyawan kasir walk-in</p>
          </div>
        </div>
        <Button
          onClick={() => setIsModalOpen(true)}
          className="flex items-center gap-1.5 text-xs py-2 px-3 shadow-sm font-bold"
          icon={<Plus size={16} />}
        >
          Tambah Staff CS
        </Button>
      </div>

      {/* Staff List Card */}
      <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-4xl w-full">
        {isLoading ? (
          <div className="p-6 flex flex-col gap-4">
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-20 w-full rounded-lg" />
          </div>
        ) : staffList.length === 0 ? (
          <div className="p-12 text-center flex flex-col items-center justify-center gap-2">
            <UserCheck size={40} className="text-slate-300" />
            <span className="text-sm font-bold text-slate-500">Belum Ada Akun Staff</span>
            <span className="text-xs text-slate-400 max-w-xs">
              Tambahkan akun staff untuk ditaruh di meja kasir/karyawan Anda agar mereka dapat membantu memproses walk-in sewa langsung.
            </span>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs">
              <thead>
                <tr className="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                  <th className="p-4">Nama</th>
                  <th className="p-4">Email</th>
                  <th className="p-4">No. Telepon</th>
                  <th className="p-4">Peran</th>
                  <th className="p-4 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
                {staffList.map((staff) => (
                  <tr key={staff.id} className="hover:bg-slate-50/50 transition-colors">
                    <td className="p-4 font-bold text-slate-800">{staff.name}</td>
                    <td className="p-4">{staff.email}</td>
                    <td className="p-4">{staff.phone}</td>
                    <td className="p-4">
                      <span className="px-2 py-1 text-xxs font-bold text-emerald-800 bg-emerald-100/60 rounded-md capitalize">
                        {staff.role}
                      </span>
                    </td>
                    <td className="p-4 text-center">
                      <button
                        onClick={() => handleDeleteStaff(staff.id)}
                        disabled={isDeleting !== null}
                        className="text-rose-600 hover:text-rose-800 disabled:text-slate-400 p-1.5 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                        title="Hapus Staff"
                      >
                        <Trash size={16} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Modal Tambah Staff */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          {/* Backdrop */}
          <div
            className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            onClick={() => setIsModalOpen(false)}
          />

          {/* Dialog Panel */}
          <div className="relative bg-white border border-slate-200 rounded-xl shadow-lg max-w-md w-full overflow-hidden z-10 animate-fade-in flex flex-col">
            <div className="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <span className="text-sm font-bold text-slate-800 flex items-center gap-1.5">
                <Users size={18} className="text-emerald-600" />
                Tambah Akun Staff Baru
              </span>
              <button
                onClick={() => setIsModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 transition-colors focus:outline-none"
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleCreateStaff} className="p-5 flex flex-col gap-4">
              <Input
                label="Nama Staff"
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Contoh: Karyawan Kasir"
                error={errors.name}
              />

              <Input
                label="Alamat Email Staff"
                type="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="emailstaff@domain.com"
                error={errors.email}
              />

              <Input
                label="No. Telepon / WhatsApp"
                type="tel"
                required
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="08xxxxxxxxxx"
                error={errors.phone}
              />

              <Input
                label="Kata Sandi Akun"
                type="password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Minimal 8 karakter"
                error={errors.password}
              />

              <div className="mt-3 flex justify-end gap-2.5">
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => setIsModalOpen(false)}
                  disabled={isSaving}
                  className="text-xs"
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  isLoading={isSaving}
                  className="text-xs font-bold"
                >
                  Buat Akun Staff
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
