import type { AuthUser } from '@/models/User'
import type { LoginRequest, LoginResponse, UpdateProfileInput } from '@/models/Auth'
import { apiClient, setAuthTokenReader } from '@/services/api/client'

export interface MfaEnrollment {
  qrCode: string
  otpauthUrl: string
  recoveryCodes: string[]
}

export interface MfaVerificationResponse {
  user: AuthUser
}

const testAuth = import.meta.env.MODE === 'test'
const authPrefix = testAuth ? '/auth' : '/admin/auth'
let testToken: string | null = null
if (testAuth) setAuthTokenReader(() => testToken)

/** Admin authentication uses an HttpOnly server session; no browser token storage. */
export const authService = {
  async login(credentials: LoginRequest): Promise<LoginResponse> {
    const response = await apiClient.post<LoginResponse>(`${authPrefix}/login`, credentials, {
      authenticated: false,
    })
    if (testAuth) testToken = response.token ?? null
    return response
  },

  async getProfile(): Promise<AuthUser | null> {
    try {
      const { user } = await apiClient.get<{ user: AuthUser }>(`${authPrefix}/me`)
      return user
    } catch (error) {
      if ((error as { status?: number }).status === 401) return null
      throw error
    }
  },

  async enrollMfa(): Promise<MfaEnrollment> {
    return apiClient.get<MfaEnrollment>(`${authPrefix}/mfa/enroll`)
  },

  async verifyMfa(code: string): Promise<MfaVerificationResponse> {
    return apiClient.post<MfaVerificationResponse>(`${authPrefix}/mfa/verify`, { code })
  },

  async verifyRecoveryCode(recoveryCode: string): Promise<MfaVerificationResponse> {
    return apiClient.post<MfaVerificationResponse>(`${authPrefix}/mfa/verify`, { recoveryCode })
  },

  async updateProfile(input: UpdateProfileInput): Promise<AuthUser> {
    const { user } = await apiClient.patch<{ user: AuthUser }>(`${authPrefix}/me`, input)
    return user
  },

  async logout(): Promise<void> {
    await apiClient.post<void>(`${authPrefix}/logout`)
    if (testAuth) testToken = null
  },
}
