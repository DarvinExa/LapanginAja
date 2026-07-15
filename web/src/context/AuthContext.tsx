import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { apiClient } from '../api/client';

export type UserRole = 'owner' | 'staff' | 'super_admin' | 'player';

export interface User {
  id: number;
  name: string;
  email: string;
  phone: string;
  role: UserRole;
  tenants?: Array<{ id: number; name: string; slug: string }>;
  created_at: string;
}

interface AuthContextType {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (credentials: any) => Promise<User>;
  register: (data: any) => Promise<User>;
  verifyLogin: (token: string, user: User) => void;
  logout: () => Promise<void>;
  refreshProfile: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(localStorage.getItem('auth_token'));
  const [isLoading, setIsLoading] = useState<boolean>(true);

  const refreshProfile = useCallback(async () => {
    try {
      const response = await apiClient.get('/auth/me');
      setUser(response.data.user);
    } catch {
      // If token is invalid or expired, clean up
      localStorage.removeItem('auth_token');
      setToken(null);
      setUser(null);
    }
  }, []);

  useEffect(() => {
    const initAuth = async () => {
      if (token) {
        await refreshProfile();
      }
      setIsLoading(false);
    };
    initAuth();
  }, [token, refreshProfile]);

  const login = async (credentials: any): Promise<User> => {
    setIsLoading(true);
    try {
      const response = await apiClient.post('/auth/login', credentials);
      const { token: receivedToken, user: receivedUser } = response.data;
      localStorage.setItem('auth_token', receivedToken);
      setToken(receivedToken);
      setUser(receivedUser);
      return receivedUser;
    } finally {
      setIsLoading(false);
    }
  };

  const register = async (data: any): Promise<User> => {
    setIsLoading(true);
    try {
      // Registration no longer logs the user in. The account must be activated
      // through the verification link sent to the user's email address.
      const response = await apiClient.post('/auth/register', data);
      return response.data.user;
    } finally {
      setIsLoading(false);
    }
  };

  const verifyLogin = (receivedToken: string, receivedUser: User) => {
    localStorage.setItem('auth_token', receivedToken);
    setToken(receivedToken);
    setUser(receivedUser);
  };

  const logout = async () => {
    setIsLoading(true);
    try {
      await apiClient.post('/auth/logout');
    } catch {
      // Even if API logout fails, clear local session
    } finally {
      localStorage.removeItem('auth_token');
      setToken(null);
      setUser(null);
      setIsLoading(false);
    }
  };

  const value = {
    user,
    token,
    isAuthenticated: !!token && !!user,
    isLoading,
    login,
    register,
    verifyLogin,
    logout,
    refreshProfile,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
