import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import type { LoginResponse } from '@/types/api.generated'

interface User {
  id: string
  username: string
  email: string
  firstName: string
  lastName: string
}

interface AuthState {
  user: User | null
  accessToken: string | null
  refreshToken: string | null
  isAuthenticated: boolean
  expiresAt?: number
  
  setAuth: (data: LoginResponse) => void
  setAccessToken: (token: string) => void
  logout: () => void
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      accessToken: null,
      refreshToken: null,
      isAuthenticated: false,

      setAuth: (data) => 
        set({ 
          user: data.user,
          accessToken: data.accessToken,
          refreshToken: data.refreshToken,
          isAuthenticated: true 
        }),

      setAccessToken: (accessToken) => 
        set({ accessToken }),

      logout: () => 
        set({ 
          user: null, 
          accessToken: null, 
          refreshToken: null, 
          isAuthenticated: false 
        }),
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({ 
        accessToken: state.accessToken,
        refreshToken: state.refreshToken,
        user: state.user,
        isAuthenticated: state.isAuthenticated
      }),
    }
  )
)

// Helper functions for API client
export function getStoredAuth() {
  const state = useAuthStore.getState()
  if (!state.isAuthenticated) return null
  return {
    accessToken: state.accessToken,
    refreshToken: state.refreshToken,
    user: state.user || undefined,
    expiresAt: state.expiresAt
  }
}

export function setStoredAuth(auth: { 
  accessToken: string, 
  refreshToken: string, 
  expiresAt?: number,
  user?: User 
}) {
  useAuthStore.setState({
    accessToken: auth.accessToken,
    refreshToken: auth.refreshToken,
    expiresAt: auth.expiresAt,
    user: auth.user || useAuthStore.getState().user,
    isAuthenticated: true
  })
}

export function clearStoredAuth() {
  useAuthStore.getState().logout()
}