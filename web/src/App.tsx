import { BrowserRouter, Routes, Route } from 'react-router-dom';
import Home from './pages/Home';
import Login from './pages/Login';
import Register from './pages/Register';
import NotFound from './pages/NotFound';
import VenueProfile from './pages/public/VenueProfile';
import BookingCheckout from './pages/public/BookingCheckout';
import BookingSuccess from './pages/public/BookingSuccess';
import AdminLayout from './components/layout/AdminLayout';
import DashboardOverview from './pages/admin/DashboardOverview';
import BookingCalendar from './pages/admin/BookingCalendar';
import BookingList from './pages/admin/BookingList';
import WalkInBooking from './pages/admin/WalkInBooking';
import CourtSettings from './pages/admin/CourtSettings';
import VenueSettings from './pages/admin/VenueSettings';
import Onboard from './pages/Onboard';
import { ProtectedRoute } from './components/auth/ProtectedRoute';
import { AuthProvider } from './context/AuthContext';
import { ToastProvider } from './context/ToastContext';
import VerifyOtp from './pages/VerifyOtp';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';
import StaffManagement from './pages/admin/StaffManagement';

function App() {
  return (
    <ToastProvider>
      <AuthProvider>
        <BrowserRouter>
          <div className="min-h-screen bg-slate-50 text-slate-900 font-sans">
            <Routes>
              {/* Public Marketing Routes */}
              <Route path="/" element={<Home />} />
              <Route path="/login" element={<Login />} />
              <Route path="/register" element={<Register />} />
              <Route path="/verify-otp" element={<VerifyOtp />} />
              <Route path="/forgot-password" element={<ForgotPassword />} />
              <Route path="/reset-password" element={<ResetPassword />} />
              <Route
                path="/onboard"
                element={
                  <ProtectedRoute allowedRoles={['owner']}>
                    <Onboard />
                  </ProtectedRoute>
                }
              />

              {/* Public Booking Flows */}
              <Route path="/:slug" element={<VenueProfile />} />
              <Route path="/:slug/bookings/:code" element={<BookingCheckout />} />
              <Route path="/:slug/bookings/:code/success" element={<BookingSuccess />} />

              {/* Protected Tenant Admin Dashboard */}
              <Route
                path="/admin/:slug"
                element={
                  <ProtectedRoute allowedRoles={['owner', 'staff']}>
                    <AdminLayout />
                  </ProtectedRoute>
                }
              >
                <Route index element={<DashboardOverview />} />
                <Route path="calendar" element={<BookingCalendar />} />
                <Route path="bookings" element={<BookingList />} />
                <Route path="walk-in" element={<WalkInBooking />} />
                <Route path="courts" element={<CourtSettings />} />
                <Route path="staff" element={<StaffManagement />} />
                <Route path="settings" element={<VenueSettings />} />
              </Route>

              {/* Fallback */}
              <Route path="*" element={<NotFound />} />
            </Routes>
          </div>
        </BrowserRouter>
      </AuthProvider>
    </ToastProvider>
  );
}

export default App;
