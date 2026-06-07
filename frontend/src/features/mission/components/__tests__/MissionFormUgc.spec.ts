import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createTestingPinia } from '@pinia/testing'
import { createRouter, createWebHistory } from 'vue-router'
import MissionForm from '../MissionForm.vue'

/**
 * Intégration MissionForm × UGC (story 1.4 AC1/2/3/5/7/8).
 * Mocks calqués sur MissionFormLocation.spec.ts + capture du payload via vi.hoisted
 * (le factory vi.mock est hoisté → il ne peut pas référencer une const non-hoistée).
 */
const { createMissionMock } = vi.hoisted(() => ({
  createMissionMock: vi.fn().mockResolvedValue({ success: true, data: { status: 'pending_payment' } }),
}))

vi.mock('../../composables/useMissionCreate', () => ({
  useMissionCreate: () => ({
    createMission: createMissionMock,
    isSubmitting: { value: false },
    error: { value: null },
    errorCode: { value: null },
  }),
}))

vi.mock('@/features/auth/services/authApi', () => ({
  authApi: { resendVerificationEmail: vi.fn() },
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}))

const router = createRouter({
  history: createWebHistory(),
  routes: [{ path: '/', component: { template: '<div />' } }],
})

// vee-validate submit validation needs a macrotask tick, not just microtask flushes.
const waitForValidation = async () => {
  await flushPromises()
  await nextTick()
  await new Promise((resolve) => setTimeout(resolve, 10))
  await flushPromises()
}

const iso = (daysAhead: number): string => {
  const d = new Date()
  d.setDate(d.getDate() + daysAhead)
  return d.toISOString().slice(0, 10)
}

function mountForm({
  mode = 'create',
  initialValues = {},
}: { mode?: 'create' | 'edit'; initialValues?: Record<string, unknown> } = {}) {
  return mount(MissionForm, {
    props: { mode, initialValues },
    global: {
      plugins: [router, createTestingPinia({ createSpy: vi.fn })],
      stubs: { NotificationBell: true },
    },
  })
}

/** Fills the shared required fields (titre, description, dates, profil, lieu). */
async function fillStandardFields(wrapper: ReturnType<typeof mountForm>) {
  await wrapper.find('#titre').setValue('Campagne sérum')
  await wrapper.find('#description').setValue('Description détaillée valide')
  await wrapper.find('#profil_recherche').setValue('Profil influenceuse beauté')
  await wrapper.find('select#lieu').setValue('Cotonou')
  await wrapper.find('#date_limite_candidature').setValue(iso(30))
  await wrapper.find('#date_tournage').setValue(iso(60))
}

/** Selects UGC + fills the UGC dotation fields, ready to submit. */
async function fillValidUgcForm(
  wrapper: ReturnType<typeof mountForm>,
  { hybrid = false }: { hybrid?: boolean } = {},
) {
  await wrapper.find('select#type_mission').setValue('ugc')
  await flushPromises()
  await fillStandardFields(wrapper)
  await wrapper.find('#nom_produit').setValue('Sérum éclat')
  await wrapper.find('#valeur_produit').setValue('25000')

  if (hybrid) {
    await wrapper.find('[data-testid="compensation-hybrid"]').trigger('click')
    await flushPromises()
    await wrapper.find('#nombre_videos').setValue('3')
    await wrapper.find('#montant_remuneration').setValue('15000')
  }
  await flushPromises()
}

describe('MissionForm — UGC integration', () => {
  beforeEach(async () => {
    createMissionMock.mockClear()
    createMissionMock.mockResolvedValue({ success: true, data: { status: 'pending_payment' } })
    await router.push('/')
    await router.isReady()
  })

  it('1. mode create : le select type_mission expose une option "ugc"', () => {
    const wrapper = mountForm({ mode: 'create' })
    const options = wrapper.findAll('select#type_mission option')
    const ugcOption = options.find((o) => o.element.value === 'ugc')
    expect(ugcOption).toBeDefined()
    expect(ugcOption!.text()).toBe('UGC')
  })

  it('2. mode edit : le select type_mission n\'expose PAS "ugc" (blast-radius, D-1.4.c)', () => {
    const wrapper = mountForm({ mode: 'edit' })
    const options = wrapper.findAll('select#type_mission option')
    expect(options.some((o) => o.element.value === 'ugc')).toBe(false)
  })

  it('3. sélection UGC → bloc dotation + récap visibles, budget + hint masqués', async () => {
    const wrapper = mountForm({ mode: 'create' })

    await wrapper.find('select#type_mission').setValue('ugc')
    await flushPromises()

    expect(wrapper.find('[data-testid="ugc-dotation-fields"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="commission-breakdown"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="budget-input"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="pricing-hint"]').exists()).toBe(false)
  })

  it('4. en UGC, le CTA affiche "Payer la commission"', async () => {
    const wrapper = mountForm({ mode: 'create' })

    await wrapper.find('select#type_mission').setValue('ugc')
    await flushPromises()

    expect(wrapper.find('[data-testid="submit-button"]').text()).toContain('Payer la commission')
  })

  it('5. UGC product verrouille "2 vidéos" ; hybrid révèle nombre_videos + rémunération', async () => {
    const wrapper = mountForm({ mode: 'create' })

    await wrapper.find('select#type_mission').setValue('ugc')
    await flushPromises()

    expect(wrapper.text()).toContain('2 vidéos')
    expect(wrapper.find('#nombre_videos').exists()).toBe(false)
    expect(wrapper.find('#montant_remuneration').exists()).toBe(false)

    await wrapper.find('[data-testid="compensation-hybrid"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('#nombre_videos').exists()).toBe(true)
    expect(wrapper.find('#montant_remuneration').exists()).toBe(true)
  })

  it('6. soumission UGC product → payload SANS budget ni champs hybrides', async () => {
    const wrapper = mountForm({ mode: 'create' })

    await fillValidUgcForm(wrapper)
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(createMissionMock).toHaveBeenCalledTimes(1)
    const payload = createMissionMock.mock.calls[0][0]
    expect(payload).toMatchObject({
      type_mission: 'ugc',
      type_compensation: 'product',
      nom_produit: 'Sérum éclat',
      valeur_produit: 25000,
    })
    expect(payload).not.toHaveProperty('budget')
    expect(payload).not.toHaveProperty('nombre_videos')
    expect(payload).not.toHaveProperty('montant_remuneration')
    expect(wrapper.emitted('success')).toBeTruthy()
  })

  it('7. soumission UGC hybride → payload avec nombre_videos + montant_remuneration', async () => {
    const wrapper = mountForm({ mode: 'create' })

    await fillValidUgcForm(wrapper, { hybrid: true })
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(createMissionMock).toHaveBeenCalledTimes(1)
    const payload = createMissionMock.mock.calls[0][0]
    expect(payload).toMatchObject({
      type_mission: 'ugc',
      type_compensation: 'hybrid',
      valeur_produit: 25000,
      nombre_videos: 3,
      montant_remuneration: 15000,
    })
    expect(payload).not.toHaveProperty('budget')
  })

  it('8. mode create type standard : pas de champs UGC, CTA "Publier la mission", budget présent', () => {
    const wrapper = mountForm({ mode: 'create' })

    expect(wrapper.find('[data-testid="ugc-dotation-fields"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="commission-breakdown"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="budget-input"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="submit-button"]').text()).toContain('Publier la mission')
  })

  it('9. une erreur 422 sur un champ UGC est mappée inline (validFields étendu — AC7)', async () => {
    createMissionMock.mockResolvedValue({
      success: false,
      errors: { valeur_produit: ['Valeur produit invalide côté serveur'] },
    })
    const wrapper = mountForm({ mode: 'create' })

    await fillValidUgcForm(wrapper)
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(createMissionMock).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[data-testid="valeur_produit-error"]').text()).toContain(
      'Valeur produit invalide côté serveur',
    )
  })

  it('10. une erreur 422 sur type_compensation est affichée sous le toggle (AC7)', async () => {
    createMissionMock.mockResolvedValue({
      success: false,
      errors: { type_compensation: ['Type de compensation invalide côté serveur'] },
    })
    const wrapper = mountForm({ mode: 'create' })

    await fillValidUgcForm(wrapper)
    await wrapper.find('form').trigger('submit')
    await waitForValidation()

    expect(createMissionMock).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[data-testid="type_compensation-error"]').text()).toContain(
      'Type de compensation invalide côté serveur',
    )
  })
})
