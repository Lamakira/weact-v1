import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { ref } from 'vue'
import type { AdminUgcSuspension } from '@/features/admin/services/adminUgcSuspensionsApi'
import type { PaginationMeta } from '@/features/admin/services/adminFinanceApi'
import AdminUgcSuspensionsPage from '../AdminUgcSuspensionsPage.vue'

const mockFetchSuspensions = vi.fn()
const mockReactivate = vi.fn()
const mockRejectAppeal = vi.fn()
const mockToastSuccess = vi.fn()
const mockToastError = vi.fn()

const suspensionsRef = ref<AdminUgcSuspension[]>([])
const paginationRef = ref<PaginationMeta | null>(null)
const currentPageRef = ref(1)
const isLoadingRef = ref(false)
const isActingRef = ref(false)
const errorRef = ref<string | null>(null)
const actionErrorRef = ref<string | null>(null)
const actionSuccessRef = ref<string | null>(null)
const wrappers: VueWrapper[] = []

vi.mock('@/features/admin/composables/useAdminUgcSuspensions', () => ({
  useAdminUgcSuspensions: () => ({
    suspensions: suspensionsRef,
    pagination: paginationRef,
    currentPage: currentPageRef,
    isLoading: isLoadingRef,
    isActing: isActingRef,
    error: errorRef,
    actionError: actionErrorRef,
    actionSuccess: actionSuccessRef,
    fetchSuspensions: mockFetchSuspensions,
    reactivate: mockReactivate,
    rejectAppeal: mockRejectAppeal,
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: mockToastSuccess, error: mockToastError }),
}))

function makeSuspension(overrides: Partial<AdminUgcSuspension> = {}): AdminUgcSuspension {
  return {
    uuid: 'susp-uuid-1',
    reason: 'avis_deadline_missed',
    reason_label: 'Avis non livré dans les délais',
    suspended_at: '2026-06-18T09:00:00+00:00',
    reactivated_at: null,
    appeal_status: 'pending',
    appeal_status_label: 'En attente',
    face: { id: 1, prenom: 'Aïcha', nom: 'Bello' },
    deal: { owner_kind: 'booking', product_name: 'Tenue Shade Fit' },
    ...overrides,
  }
}

describe('AdminUgcSuspensionsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    document.body.innerHTML = ''
    suspensionsRef.value = []
    paginationRef.value = null
    currentPageRef.value = 1
    isLoadingRef.value = false
    isActingRef.value = false
    errorRef.value = null
    actionErrorRef.value = null
    actionSuccessRef.value = null
  })

  afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
    document.body.innerHTML = ''
  })

  it('mount_calls_fetchSuspensions_on_mount', async () => {
    wrappers.push(mount(AdminUgcSuspensionsPage))
    await flushPromises()

    expect(mockFetchSuspensions).toHaveBeenCalledTimes(1)
  })

  it('displays_empty_state_when_no_suspensions', async () => {
    suspensionsRef.value = []
    const wrapper = mount(AdminUgcSuspensionsPage)
    wrappers.push(wrapper)
    await flushPromises()

    expect(wrapper.text()).toContain('Aucun appel en attente.')
  })

  it('displays_fetch_error_when_loading_fails', async () => {
    errorRef.value = 'Impossible de charger les appels.'
    const wrapper = mount(AdminUgcSuspensionsPage)
    wrappers.push(wrapper)
    await flushPromises()

    expect(wrapper.text()).toContain('Impossible de charger les appels.')
  })

  it('displays_suspensions_table_when_data_present', async () => {
    suspensionsRef.value = [
      makeSuspension({ uuid: 'susp-1' }),
      makeSuspension({ uuid: 'susp-2', face: { id: 2, prenom: 'Koffi', nom: 'Doe' } }),
    ]
    const wrapper = mount(AdminUgcSuspensionsPage)
    wrappers.push(wrapper)
    await flushPromises()

    const rows = wrapper.findAll('[data-testid="ugc-suspension-row"]')
    expect(rows.length).toBe(2)
    expect(wrapper.text()).toContain('Aïcha')
    expect(wrapper.text()).toContain('Tenue Shade Fit')
  })

  it('clicking_refresh_calls_fetchSuspensions_again', async () => {
    const wrapper = mount(AdminUgcSuspensionsPage)
    wrappers.push(wrapper)
    await flushPromises()
    mockFetchSuspensions.mockClear()

    const refreshBtn = wrapper.find('button')
    await refreshBtn.trigger('click')

    expect(mockFetchSuspensions).toHaveBeenCalledTimes(1)
  })

  it('confirming_reactivate_modal_calls_reactivate_and_toasts_success', async () => {
    suspensionsRef.value = [makeSuspension({ uuid: 'susp-7' })]
    mockReactivate.mockResolvedValue(true)
    actionSuccessRef.value = 'Compte Face réactivé.'

    const wrapper = mount(AdminUgcSuspensionsPage, {
      attachTo: document.body,
    })
    wrappers.push(wrapper)
    await flushPromises()

    await wrapper.get('[data-testid="ugc-suspension-reactivate-btn"]').trigger('click')
    await flushPromises()

    const confirmBtn = document.body.querySelector<HTMLButtonElement>(
      '[data-testid="ugc-suspension-confirm-btn"]',
    )
    expect(confirmBtn).not.toBeNull()
    confirmBtn!.click()
    await flushPromises()

    expect(mockReactivate).toHaveBeenCalledWith('susp-7')
    expect(mockToastSuccess).toHaveBeenCalledWith('Compte Face réactivé.')
  })

  it('confirming_reject_modal_calls_rejectAppeal_and_toasts_success', async () => {
    suspensionsRef.value = [makeSuspension({ uuid: 'susp-9' })]
    mockRejectAppeal.mockResolvedValue(true)
    actionSuccessRef.value = 'Appel rejeté — la Face reste suspendue.'

    const wrapper = mount(AdminUgcSuspensionsPage, {
      attachTo: document.body,
    })
    wrappers.push(wrapper)
    await flushPromises()

    await wrapper.get('[data-testid="ugc-suspension-reject-btn"]').trigger('click')
    await flushPromises()

    const confirmBtn = document.body.querySelector<HTMLButtonElement>(
      '[data-testid="ugc-suspension-confirm-btn"]',
    )
    expect(confirmBtn).not.toBeNull()
    confirmBtn!.click()
    await flushPromises()

    expect(mockRejectAppeal).toHaveBeenCalledWith('susp-9')
    expect(mockReactivate).not.toHaveBeenCalled()
    expect(mockToastSuccess).toHaveBeenCalledWith('Appel rejeté — la Face reste suspendue.')
  })
})
