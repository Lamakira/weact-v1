import apiClient, { getCsrfCookie } from '@/services/apiClient'

interface EmailChangeStatusResponse {
  pending_email: string | null
}

interface EmailChangeConfirmResponse {
  email_changed: boolean
}

export const emailChangeApi = {
  async requestChange(
    email: string,
    password: string,
  ): Promise<{ pending_email: string }> {
    await getCsrfCookie()
    const response = await apiClient.post<{
      data: { pending_email: string }
      message: string
    }>('/email/change', { email, password })
    return response.data.data
  },

  async confirmChange(
    id: string,
    hash: string,
    expires: string,
    signature: string,
  ): Promise<EmailChangeConfirmResponse> {
    const response = await apiClient.get<{
      data: EmailChangeConfirmResponse
      message: string
    }>(`/auth/email/change/confirm/${id}/${hash}`, {
      params: { expires, signature },
    })
    return response.data.data
  },

  async cancelChange(): Promise<void> {
    await getCsrfCookie()
    await apiClient.delete('/email/change')
  },

  async getStatus(): Promise<EmailChangeStatusResponse> {
    const response = await apiClient.get<{
      data: EmailChangeStatusResponse
      message: string
    }>('/email/change/status')
    return response.data.data
  },
}
