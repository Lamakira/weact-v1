import { publicApiClient } from './apiClient'

interface ContactFormData {
  name: string
  email: string
  subject: string
  message: string
}

interface ContactFormResponse {
  message: string
}

export async function submitContactForm(data: ContactFormData): Promise<ContactFormResponse> {
  const response = await publicApiClient.post<ContactFormResponse>('/public/contact', data)
  return response.data
}
