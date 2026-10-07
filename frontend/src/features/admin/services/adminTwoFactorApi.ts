import adminApiClient, { getCsrfCookie } from './adminApiClient'

export interface AdminTwoFactorStatus {
  enabled: boolean
  pending: boolean
  recovery_codes_remaining: number
}

export interface AdminTwoFactorSetup {
  secret: string
  otpauth_uri: string
  /** Server-generated SVG (QR code of the otpauth URI) */
  qr_svg: string
}

export interface AdminTwoFactorReauthForm {
  password: string
  code?: string
  recovery_code?: string
}

/**
 * Admin TOTP enrolment + management API service
 */
export const adminTwoFactorApi = {
  async getStatus(): Promise<AdminTwoFactorStatus> {
    const response = await adminApiClient.get<{ data: AdminTwoFactorStatus }>('/admin/two-factor')
    return response.data.data
  },

  /** Start enrolment: returns the secret, the otpauth URI and the QR code */
  async enable(): Promise<AdminTwoFactorSetup> {
    await getCsrfCookie()
    const response = await adminApiClient.post<{ data: AdminTwoFactorSetup }>(
      '/admin/two-factor/enable',
    )
    return response.data.data
  },

  /** Confirm enrolment with a valid code: returns the recovery codes (shown once) */
  async confirm(code: string): Promise<string[]> {
    await getCsrfCookie()
    const response = await adminApiClient.post<{ data: { recovery_codes: string[] } }>(
      '/admin/two-factor/confirm',
      { code },
    )
    return response.data.data.recovery_codes
  },

  async disable(data: AdminTwoFactorReauthForm): Promise<void> {
    await getCsrfCookie()
    await adminApiClient.post('/admin/two-factor/disable', data)
  },

  /** Regenerate recovery codes (invalidates the previous ones) */
  async regenerateRecoveryCodes(data: AdminTwoFactorReauthForm): Promise<string[]> {
    await getCsrfCookie()
    const response = await adminApiClient.post<{ data: { recovery_codes: string[] } }>(
      '/admin/two-factor/recovery-codes',
      data,
    )
    return response.data.data.recovery_codes
  },
}
