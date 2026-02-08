import { publicApiClient } from './apiClient'

export interface ContactFormData {
  name: string
  email: string
  subject: string
  message: string
}

export interface ContactFormResponse {
  message: string
}

export async function submitContactForm(data: ContactFormData): Promise<ContactFormResponse> {
  const response = await publicApiClient.post<ContactFormResponse>('/public/contact', data)
  return response.data
}
