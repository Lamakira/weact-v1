import apiClient, { getCsrfCookie } from '@/services/apiClient'

export const passwordChangeApi = {
  async changePassword(
    currentPassword: string,
    newPassword: string,
    newPasswordConfirmation: string,
  ): Promise<{ password_changed: boolean }> {
    await getCsrfCookie()
    const response = await apiClient.put<{
      data: { password_changed: boolean }
      message: string
    }>('/password', {
      current_password: currentPassword,
      new_password: newPassword,
      new_password_confirmation: newPasswordConfirmation,
    })
    return response.data.data
  },
}
