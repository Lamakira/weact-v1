import apiClient, { getCsrfCookie } from '@/services/apiClient'
import type { ContractTemplate, SignatureCodePayload, SignatureCodeResponse } from '../types'

export const imageContractApi = {
  /** Texte officiel du contrat (13 articles), source unique côté serveur. */
  async getTemplate(): Promise<ContractTemplate> {
    const response = await apiClient.get<ContractTemplate | { data: ContractTemplate }>('/image-contract/template')
    const body = response.data
    return 'data' in body ? body.data : body
  },

  /** Envoie un code de signature à 6 chiffres par e-mail (Producteur, Face ou parent). */
  async requestCode(payload: SignatureCodePayload): Promise<SignatureCodeResponse> {
    await getCsrfCookie()
    const response = await apiClient.post<SignatureCodeResponse>('/image-contract/code', payload)
    return response.data
  },

  /** PDF du contrat signé (parties seulement). */
  async downloadPdf(bookingId: string): Promise<Blob> {
    const response = await apiClient.get<Blob>(`/bookings/${bookingId}/image-contract/pdf`, {
      responseType: 'blob',
    })
    return response.data
  },
}
