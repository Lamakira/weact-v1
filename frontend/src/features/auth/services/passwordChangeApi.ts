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

  /**
   * First password of a password-less (Google-created) account: confirmed by a
   * fresh Google re-auth ticket, no `current_password` key at all.
   */
  async setPassword(
    newPassword: string,
    newPasswordConfirmation: string,
    reauthToken: string,
  ): Promise<{ password_changed: boolean }> {
    await getCsrfCookie()
    const response = await apiClient.put<{
      data: { password_changed: boolean }
      message: string
    }>('/password', {
      new_password: newPassword,
      new_password_confirmation: newPasswordConfirmation,
      reauth_token: reauthToken,
    })
    return response.data.data
  },
}
