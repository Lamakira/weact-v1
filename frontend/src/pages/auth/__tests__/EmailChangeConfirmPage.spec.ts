import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import EmailChangeConfirmPage from '../EmailChangeConfirmPage.vue'
import { emailChangeApi } from '@/features/auth/services/emailChangeApi'
import { getApiErrorCode } from '@/features/auth/services/authApi'

// Mock emailChangeApi
vi.mock('@/features/auth/services/emailChangeApi', () => ({
  emailChangeApi: {
    confirmChange: vi.fn(),
  },
}))

// Mock getApiErrorCode
vi.mock('@/features/auth/services/authApi', () => ({
  getApiErrorCode: vi.fn(),
}))

const mockConfirmChange = vi.mocked(emailChangeApi.confirmChange)
const mockGetApiErrorCode = vi.mocked(getApiErrorCode)

// Mock vue-router
const mockRoute = {
  params: { id: '123', hash: 'abc123' },
  query: { expires: '1700000000', signature: 'sig123' },
}
const mockRouterPush = vi.fn()
vi.mock('vue-router', () => ({
  useRoute: () => mockRoute,
  useRouter: () => ({
    push: mockRouterPush,
  }),
}))

describe('EmailChangeConfirmPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockRoute.params = { id: '123', hash: 'abc123' }
    mockRoute.query = { expires: '1700000000', signature: 'sig123' }
  })

  it('shows loading state initially', () => {
    mockConfirmChange.mockReturnValue(new Promise(() => {})) // never resolves

    const wrapper = mount(EmailChangeConfirmPage)

    expect(wrapper.find('[data-testid="confirm-loading"]').exists()).toBe(true)
  })

  it('shows success state on successful confirmation', async () => {
    mockConfirmChange.mockResolvedValue({ email_changed: true })

    const wrapper = mount(EmailChangeConfirmPage)
    await flushPromises()

    expect(wrapper.find('[data-testid="confirm-success"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="confirm-loading"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Email mis à jour')
  })

  it('calls confirmChange with correct params', async () => {
    mockConfirmChange.mockResolvedValue({ email_changed: true })

    mount(EmailChangeConfirmPage)
    await flushPromises()

    expect(mockConfirmChange).toHaveBeenCalledWith('123', 'abc123', '1700000000', 'sig123')
  })

  it('navigates to login when go-to-login button is clicked', async () => {
    mockConfirmChange.mockResolvedValue({ email_changed: true })

    const wrapper = mount(EmailChangeConfirmPage)
    await flushPromises()

    await wrapper.find('[data-testid="go-to-login-button"]').trigger('click')

    expect(mockRouterPush).toHaveBeenCalledWith({ name: 'login' })
  })

  describe('error states', () => {
    it('shows error when link params are missing', async () => {
      mockRoute.params = { id: '', hash: 'abc123' }

      const wrapper = mount(EmailChangeConfirmPage)
      await flushPromises()

      expect(wrapper.find('[data-testid="confirm-error"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="error-message"]').text()).toContain(
        'Lien de confirmation invalide ou incomplet',
      )
      expect(mockConfirmChange).not.toHaveBeenCalled()
    })

    it('shows expired link error', async () => {
      mockGetApiErrorCode.mockReturnValue('CONFIRMATION_LINK_EXPIRED')
      mockConfirmChange.mockRejectedValue(new Error('expired'))

      const wrapper = mount(EmailChangeConfirmPage)
      await flushPromises()

      expect(wrapper.find('[data-testid="confirm-error"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="error-message"]').text()).toContain('expiré')
    })

    it('shows invalid link error', async () => {
      mockGetApiErrorCode.mockReturnValue('INVALID_CONFIRMATION_LINK')
      mockConfirmChange.mockRejectedValue(new Error('invalid'))

      const wrapper = mount(EmailChangeConfirmPage)
      await flushPromises()

      expect(wrapper.find('[data-testid="confirm-error"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="error-message"]').text()).toContain('invalide')
    })

    it('shows no pending change error', async () => {
      mockGetApiErrorCode.mockReturnValue('NO_PENDING_EMAIL_CHANGE')
      mockConfirmChange.mockRejectedValue(new Error('no pending'))

      const wrapper = mount(EmailChangeConfirmPage)
      await flushPromises()

      expect(wrapper.find('[data-testid="confirm-error"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="error-message"]').text()).toContain(
        'Aucun changement',
      )
    })

    it('shows email taken error', async () => {
      mockGetApiErrorCode.mockReturnValue('EMAIL_ALREADY_TAKEN')
      mockConfirmChange.mockRejectedValue(new Error('taken'))

      const wrapper = mount(EmailChangeConfirmPage)
      await flushPromises()

      expect(wrapper.find('[data-testid="confirm-error"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="error-message"]').text()).toContain(
        'utilisée par un autre compte',
      )
    })

    it('shows generic error for unknown error code', async () => {
      mockGetApiErrorCode.mockReturnValue(null)
      mockConfirmChange.mockRejectedValue(new Error('unknown'))

      const wrapper = mount(EmailChangeConfirmPage)
      await flushPromises()

      expect(wrapper.find('[data-testid="confirm-error"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="error-message"]').text()).toContain(
        'Une erreur est survenue',
      )
    })

    it('shows back button on error that navigates to login', async () => {
      mockGetApiErrorCode.mockReturnValue(null)
      mockConfirmChange.mockRejectedValue(new Error('error'))

      const wrapper = mount(EmailChangeConfirmPage)
      await flushPromises()

      await wrapper.find('[data-testid="back-button"]').trigger('click')

      expect(mockRouterPush).toHaveBeenCalledWith({ name: 'login' })
    })
  })
})
