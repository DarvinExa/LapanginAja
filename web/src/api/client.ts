import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1';

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Interceptor to add Sanctum token to requests
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Interceptor to handle global errors (e.g. 401 redirect to login)
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;
    const data = error.response?.data;
    const message = data?.message || 'Terjadi kesalahan pada server.';

    if (status === 401) {
      localStorage.removeItem('auth_token');
      const isLoginRequest = error.config?.url?.endsWith('/auth/login');
      if (!isLoginRequest) {
        window.dispatchEvent(
          new CustomEvent('app-toast', {
            detail: { message: 'Sesi Anda telah berakhir. Silakan login kembali.', type: 'error' },
          })
        );
        if (!window.location.pathname.includes('/login')) {
          window.location.href = '/login';
        }
      }
    } else if (status !== 422) {
      window.dispatchEvent(
        new CustomEvent('app-toast', {
          detail: { message, type: 'error' },
        })
      );
    }
    return Promise.reject(error);
  }
);
