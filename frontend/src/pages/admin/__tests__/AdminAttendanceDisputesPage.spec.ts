import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { ref } from 'vue'
import type { AdminDispute } from '@/features/admin/services/adminAttendanceDisputesApi'
import type { PaginationMeta } from '@/features/admin/services/adminFinanceApi'
import AdminAttendanceDisputesPage from '../AdminAttendanceDisputesPage.vue'

const mockFetchDisputes = vi.fn()
const mockResolveDispute = vi.fn()
const mockToastSuccess = vi.fn()
const mockToastError = vi.fn()

const disputesRef = ref<AdminDispute[]>([])
const paginationRef = ref<PaginationMeta | null>(null)
const currentPageRef = ref(1)
const isLoadingRef = ref(false)
const isResolvingRef = ref(false)
const errorRef = ref<string | null>(null)
const resolveErrorRef = ref<string | null>(null)
const resolveSuccessRef = ref<string | null>(null)
const wrappers: VueWrapper[] = []

vi.mock('@/features/admin/composables/useAdminAttendanceDisputes', () => ({
  useAdminAttendanceDisputes: () => ({
    disputes: disputesRef,
    pagination: paginationRef,
    currentPage: currentPageRef,
    isLoading: isLoadingRef,
    isResolving: isResolvingRef,
    error: errorRef,
    resolveError: resolveErrorRef,
    resolveSuccess: resolveSuccessRef,
    fetchDisputes: mockFetchDisputes,
    resolveDispute: mockResolveDispute,
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: mockToastSuccess, error: mockToastError }),
}))

function makeDispute(overrides: Partial<AdminDispute> = {}): AdminDispute {
  return {
    id: 1,
    mission: {
      id: 'mission-uuid',
      titre: 'Pub TV',
      date_tournage: '2026-04-30T00:00:00.000Z',
      producer: { id: 'producer-uuid', display_name: 'Studio Lumière' },
    },
    face: {
      id: 'face-uuid',
      display_name: 'Amina K.',
      profile_photo_url: null,
      profile_photo_thumbnail_url: null,
    },
    montant_face_recoit: 90000,
    attendance_status: 'disputed',
    escrow_status: 'locked',
    notified_at: '2026-04-29T08:00:00.000Z',
    disputed_at: '2026-04-29T10:00:00.000Z',
    ...overrides,
  }
}

describe('AdminAttendanceDisputesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    document.body.innerHTML = ''
    disputesRef.value = []
    paginationRef.value = null
    currentPageRef.value = 1
    isLoadingRef.value = false
    isResolvingRef.value = false
    errorRef.value = null
    resolveErrorRef.value = null
    resolveSuccessRef.value = null
  })

  afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
    document.body.innerHTML = ''
  })

  it('mount_calls_fetchDisputes_on_mount', async () => {
    wrappers.push(mount(AdminAttendanceDisputesPage))
    await flushPromises()

    expect(mockFetchDisputes).toHaveBeenCalledTimes(1)
  })

  it('displays_empty_state_when_no_disputes', async () => {
    disputesRef.value = []
    const wrapper = mount(AdminAttendanceDisputesPage)
    wrappers.push(wrapper)
    await flushPromises()

    expect(wrapper.text()).toContain('Aucun litige en attente.')
  })

  it('displays_fetch_error_when_loading_fails', async () => {
    errorRef.value = 'Impossible de charger les litiges.'
    const wrapper = mount(AdminAttendanceDisputesPage)
    wrappers.push(wrapper)
    await flushPromises()

    expect(wrapper.text()).toContain('Impossible de charger les litiges.')
  })

  it('displays_disputes_table_when_data_present', async () => {
    disputesRef.value = [makeDispute({ id: 1 }), makeDispute({ id: 2 })]
    const wrapper = mount(AdminAttendanceDisputesPage)
    wrappers.push(wrapper)
    await flushPromises()

    const rows = wrapper.findAll('tbody tr')
    expect(rows.length).toBe(2)
    expect(wrapper.text()).toContain('Studio Lumière')
    expect(wrapper.text()).toContain('Amina K.')
  })

  it('clicking_refresh_calls_fetchDisputes_again', async () => {
    const wrapper = mount(AdminAttendanceDisputesPage)
    wrappers.push(wrapper)
    await flushPromises()
    mockFetchDisputes.mockClear()

    const refreshBtn = wrapper.find('button')
    await refreshBtn.trigger('click')

    expect(mockFetchDisputes).toHaveBeenCalledTimes(1)
  })

  it('submits_modal_resolution_with_trimmed_notes', async () => {
    disputesRef.value = [makeDispute({ id: 7 })]
    mockResolveDispute.mockResolvedValue(true)
    resolveSuccessRef.value = 'Litige résolu avec succès'

    const wrapper = mount(AdminAttendanceDisputesPage, {
      attachTo: document.body,
    })
    wrappers.push(wrapper)
    await flushPromises()

    await wrapper.findAll('tbody button')[0].trigger('click')
    const textarea = document.body.querySelector<HTMLTextAreaElement>('#dispute-resolve-notes')
    expect(textarea).not.toBeNull()

    textarea!.value = '  Note décision admin  '
    textarea!.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()
    const confirmButton = Array.from(document.body.querySelectorAll('button'))
      .find((button) => button.textContent?.includes('Confirmer'))
    expect(confirmButton).toBeDefined()
    confirmButton?.click()
    await flushPromises()

    expect(mockResolveDispute).toHaveBeenCalledWith(7, 'face', 'Note décision admin')
    expect(mockToastSuccess).toHaveBeenCalledWith('Litige résolu avec succès')
  })

  it('shows_refresh_failure_toast_after_committed_resolution', async () => {
    disputesRef.value = [makeDispute({ id: 7 })]
    mockResolveDispute.mockResolvedValue(true)
    resolveSuccessRef.value = 'Litige résolu avec succès'
    resolveErrorRef.value = 'Litige résolu, mais la liste n\'a pas pu être rafraîchie.'

    const wrapper = mount(AdminAttendanceDisputesPage, {
      attachTo: document.body,
    })
    wrappers.push(wrapper)
    await flushPromises()

    await wrapper.findAll('tbody button')[0].trigger('click')
    const textarea = document.body.querySelector<HTMLTextAreaElement>('#dispute-resolve-notes')
    expect(textarea).not.toBeNull()

    textarea!.value = 'Note décision admin'
    textarea!.dispatchEvent(new Event('input', { bubbles: true }))
    await flushPromises()
    const confirmButton = Array.from(document.body.querySelectorAll('button'))
      .find((button) => button.textContent?.includes('Confirmer'))
    expect(confirmButton).toBeDefined()
    confirmButton?.click()
    await flushPromises()

    expect(mockToastSuccess).toHaveBeenCalledWith('Litige résolu avec succès')
    expect(mockToastError).toHaveBeenCalledWith('Litige résolu, mais la liste n\'a pas pu être rafraîchie.')
  })
})
