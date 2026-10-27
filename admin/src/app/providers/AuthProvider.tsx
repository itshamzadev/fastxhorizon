import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import type { LoginRequest, LoginResponse } from '@/models/Auth'
import type { AuthUser } from '@/models/User'
import { authService } from '@/services/auth/auth.service'

type AuthStatus = 'loading' | 'authenticated' | 'unauthenticated'

interface AuthContextValue {
  status: AuthStatus
  user: AuthUser | null
  isAuthenticated: boolean
  login: (credentials: LoginRequest) => Promise<LoginResponse>
  completeLogin: (user: AuthUser) => void
  logout: () => Promise<void>
  refresh: () => Promise<AuthUser | null>
  setUser: (user: AuthUser) => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<AuthStatus>('loading')
  const [user, setUser] = useState<AuthUser | null>(null)

  const refresh = useCallback(async () => {
    const profile = await authService.getProfile()
    setUser(profile)
    setStatus(profile ? 'authenticated' : 'unauthenticated')
    return profile
  }, [])

  useEffect(() => {
    void refresh().catch(() => {
      setUser(null)
      setStatus('unauthenticated')
    })
  }, [refresh])

  const login = useCallback(async (credentials: LoginRequest) => {
    const response = await authService.login(credentials)
    if (response.user) {
      setUser(response.user)
      setStatus('authenticated')
    } else {
      setUser(null)
      setStatus('unauthenticated')
    }
    return response
  }, [])

  const completeLogin = useCallback((nextUser: AuthUser) => {
    setUser(nextUser)
    setStatus('authenticated')
  }, [])

  const logout = useCallback(async () => {
    try {
      await authService.logout()
    } finally {
      setUser(null)
      setStatus('unauthenticated')
    }
  }, [])

  const value = useMemo(
    () => ({
      status,
      user,
      isAuthenticated: status === 'authenticated',
      login,
      completeLogin,
      logout,
      refresh,
      setUser,
    }),
    [status, user, login, completeLogin, logout, refresh, setUser],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used within an AuthProvider')
  return context
}
