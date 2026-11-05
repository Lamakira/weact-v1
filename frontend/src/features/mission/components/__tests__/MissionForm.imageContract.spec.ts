import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createTestingPinia } from '@pinia/testing'
import { createRouter, createWebHistory } from 'vue-router'
import MissionForm from '../MissionForm.vue'
import { useAuthStore } from '@/stores/auth'
import { setSelectValue } from '@/components/ui/__tests__/selectTestUtils'

/**
 * MissionForm × autorisation d'image (incrément 2, publication) — modèle : BookingFormSheet.imageContract.spec.
 */
const { createMissionMock, clearErrorMock, requestCodeMock } = vi.hoisted(() => ({
  createMissionMock: vi.fn(),
  clearErrorMock: vi.fn(),
  requestCodeMock: vi.fn(),
}))

vi.mock('../../composables/useMissionCreate', () => ({
  useMissionCreate: () => ({
    createMission: createMissionMock,
    isSubmitting: { value: false },
    error: { value: null },
    errorCode: { value: null },
    clearError: clearErrorMock,
  }),
}))

vi.mock('@/features/auth/services/authApi', () => ({
  authApi: { resendVerificationEmail: vi.fn() },
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}))

vi.mock('@/features/imageContract/services/imageContractApi', () => ({
  imageContractApi: { requestCode: requestCodeMock, getTemplate: vi.fn() },
}))

global.URL.createObjectURL = vi.fn(() => 'blob:mock-url')
global.URL.revokeObjectURL = vi.fn()

const router = createRouter({
  history: createWebHistory(),
  routes: [{ path: '/', component: { template: '<div />' } }],
})

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

const standardValues = () => ({
  titre: 'Spot TV jus de bissap',
  description: 'Description détaillée valide',
  profil_recherche: 'Face polyvalente',
  budget: 50000,
  nombre_faces_voulu: 2,
  type_mission: 'publicite',
  genre_voulu: 'tous',
  lieu: 'Cotonou',
  date_limite_candidature: iso(30),
  date_tournage: iso(40),
})

function mountForm({
  mode = 'create',
  flag = true,
  initialValues = standardValues() as Record<string, unknown>,
}: { mode?: 'create' | 'edit'; flag?: boolean | null; initialValues?: Record<string, unknown> } = {}) {
  return mount(MissionForm, {
    props: { mode, initialValues },
    global: {
      plugins: [
        router,
        createTestingPinia({
          createSpy: vi.fn,
          initialState: {
            auth: {
              user: {
                userable_type: 'Producer',
                ...(flag === null ? {} : { image_contracts_enabled: flag }),
                userable: { first_name: 'Jean', last_name: 'Producteur', display_name: 'Agence J' },
              },
            },
          },
        }),
      ],
      stubs: { NotificationBell: true, Teleport: { template: '<slot />' } },
    },
  })
}

type Wrapper = ReturnType<typeof mountForm>

const pickSupport = (wrapper: Wrapper, value: string) =>
  wrapper.find(`[data-testid="image-support-${value}"]`).trigger('click')

async function submitForm(wrapper: Wrapper) {
  await wrapper.find('form').trigger('submit')
  await waitForValidation()
}

async function openSignDialog(wrapper: Wrapper) {
  await pickSupport(wrapper, 'social')
  await submitForm(wrapper)
}

async function signWithCode(wrapper: Wrapper, code: string) {
  await wrapper.find('[data-testid="image-contract-accept"]').setValue(true)
  await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
  await flushPromises()
  const paste = new Event('paste', { bubbles: true, cancelable: true }) as Event & { clipboardData: unknown }
  paste.clipboardData = { getData: () => code }
  wrapper.find('[data-testid="code-digit-0"]').element.dispatchEvent(paste)
  await flushPromises()
  await wrapper.find('[data-testid="image-contract-confirm"]').trigger('click')
  await flushPromises()
}

describe('MissionForm : drapeau image_contracts_enabled faux ou section absente', () => {
  beforeEach(async () => {
    createMissionMock.mockReset()
    requestCodeMock.mockReset()
    clearErrorMock.mockReset()
    await router.push('/')
    await router.isReady()
  })

  it.each([false, null])('rendu inchangé (drapeau %s) : ni section, ni encadré, ni fenêtre', (flag) => {
    const wrapper = mountForm({ flag })

    expect(wrapper.find('[data-testid="image-terms-section"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="image-contract-code-notice"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="submit-button"]').text()).toContain('Publier la mission')
  })

  it('envoi inchangé : aucune clé image_terms ni signature (ancien chemin)', async () => {
    createMissionMock.mockResolvedValue({ success: true, data: { id: 'm-1', status: 'published' } })
    const wrapper = mountForm({ flag: false })

    await submitForm(wrapper)

    expect(createMissionMock).toHaveBeenCalledTimes(1)
    const payload = createMissionMock.mock.calls[0]![0]
    expect(payload).not.toHaveProperty('image_terms')
    expect(payload).not.toHaveProperty('signature')
    expect(wrapper.emitted('success')).toBeTruthy()
  })

  it('en édition : pas de section ni de bouton renommé, l\'émission submit est inchangée', async () => {
    const wrapper = mountForm({ mode: 'edit', flag: true })

    expect(wrapper.find('[data-testid="image-terms-section"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="image-contract-code-notice"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="submit-button"]').text()).toContain('Enregistrer les modifications')

    await submitForm(wrapper)

    const submitted = wrapper.emitted('submit')
    expect(submitted).toHaveLength(1)
    expect(submitted![0]![0]).not.toHaveProperty('image_terms')
    expect(createMissionMock).not.toHaveBeenCalled()
  })

  it('422 sur image_terms alors que la section est absente : relit le compte et explique', async () => {
    createMissionMock.mockResolvedValue({
      success: false,
      errors: { 'image_terms.finalite': ['La finalité est obligatoire.'] },
    })
    const wrapper = mountForm({ flag: false })
    const auth = useAuthStore()
    vi.mocked(auth.refreshUser).mockImplementation(async () => {
      auth.user = { ...(auth.user as object), image_contracts_enabled: true } as never
      return true
    })

    await submitForm(wrapper)
    await flushPromises()

    expect(auth.refreshUser).toHaveBeenCalled()
    expect(wrapper.find('[data-testid="image-terms-error"]').text()).toBe(
      "Une nouvelle étape est nécessaire : renseignez l'utilisation de l'image.",
    )
  })
})

describe('MissionForm : drapeau image_contracts_enabled vrai (création)', () => {
  beforeEach(async () => {
    createMissionMock.mockReset()
    requestCodeMock.mockReset()
    clearErrorMock.mockReset()
    await router.push('/')
    await router.isReady()
  })

  it('relit le compte à l\'ouverture en création', () => {
    mountForm()
    expect(useAuthStore().refreshUser).toHaveBeenCalledTimes(1)
  })

  it('affiche la section avec son sous-titre, l\'encadré info et le bouton renommé', () => {
    const wrapper = mountForm()

    const section = wrapper.find('[data-testid="image-terms-section"]')
    expect(section.text()).toContain("Utilisation de l'image")
    expect(section.find('[data-testid="image-terms-intro"]').text()).toBe(
      "Ces choix s'appliquent à toutes les Faces que vous retiendrez.",
    )
    expect(wrapper.find('[data-testid="mission-image-terms-info"]').text()).toBe(
      "Les Faces voient ces conditions avant de candidater. Elles s'appliquent à toutes les Faces retenues et ne changent plus après la publication.",
    )
    expect(wrapper.find('[data-testid="submit-button"]').text()).toContain('Lire, signer et publier')
  })

  it('place l\'encadré orange du code juste au-dessus du bouton', () => {
    const wrapper = mountForm()

    const notice = wrapper.find('[data-testid="image-contract-code-notice"]')
    expect(notice.text()).toContain('Un code vous sera envoyé par e-mail')
    const html = wrapper.html()
    expect(html.indexOf('image-contract-code-notice')).toBeLessThan(html.indexOf('data-testid="submit-button"'))
    expect(html.indexOf('image-terms-section')).toBeLessThan(html.indexOf('image-contract-code-notice'))
  })

  it('préremplit la finalité selon le type, sinon avec le titre, sans écraser une saisie', async () => {
    const standard = mountForm()
    expect((standard.find('[data-testid="image-finalite"]').element as HTMLInputElement).value).toBe('Publicité')

    const autre = mountForm({ initialValues: { ...standardValues(), type_mission: 'autre', type_mission_autre: 'Défilé' } })
    expect((autre.find('[data-testid="image-finalite"]').element as HTMLInputElement).value).toBe('Spot TV jus de bissap')

    await autre.find('[data-testid="image-finalite"]').setValue('Ma finalité')
    await autre.find('#titre').setValue('Un autre titre')
    expect((autre.find('[data-testid="image-finalite"]').element as HTMLInputElement).value).toBe('Ma finalité')
  })

  it('préremplit les conditions depuis une republication', () => {
    const wrapper = mountForm({
      initialValues: {
        ...standardValues(),
        image_terms: { finalite: 'Publicité TV', supports: ['tv'], territoire: 'world', duree_annees: 5 },
      },
    })

    expect((wrapper.find('[data-testid="image-finalite"]').element as HTMLInputElement).value).toBe('Publicité TV')
    expect(wrapper.find('[data-testid="image-support-tv"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('[data-testid="image-territoire-world"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('[data-testid="image-duree-5"]').attributes('aria-pressed')).toBe('true')
  })

  it('refuse d\'ouvrir la signature sans support choisi', async () => {
    const wrapper = mountForm()

    await submitForm(wrapper)

    expect(wrapper.find('[data-testid="image-terms-error"]').text()).toBe('Choisissez au moins un support.')
    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
    expect(createMissionMock).not.toHaveBeenCalled()
  })

  it('refuse d\'ouvrir la signature avec une finalité vide', async () => {
    const wrapper = mountForm()

    await pickSupport(wrapper, 'social')
    await wrapper.find('[data-testid="image-finalite"]').setValue('   ')
    await submitForm(wrapper)

    expect(wrapper.find('[data-testid="image-terms-error"]').text()).toContain('finalité')
    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
  })

  it('ouvre la fenêtre de signature sans créer la mission, résumé et nom préremplis', async () => {
    const wrapper = mountForm()

    await openSignDialog(wrapper)

    expect(createMissionMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(true)
    expect((wrapper.find('[data-testid="image-contract-name"]').element as HTMLInputElement).value).toBe('Jean Producteur')
    expect(wrapper.find('[data-testid="summary-projet"]').text()).toBe('Spot TV jus de bissap')
    expect(wrapper.find('[data-testid="summary-supports"]').text()).toBe('Réseaux sociaux')
    expect(wrapper.find('[data-testid="summary-contrepartie"]').text()).toContain('par Face')
  })

  it('envoie la mission avec image_terms et signature après saisie du code', async () => {
    requestCodeMock.mockResolvedValue({ data: { sent_to: 'j•••@gmail.com', expires_at: 'x', retry_after: 60 } })
    createMissionMock.mockResolvedValue({ success: true, data: { id: 'm-1', status: 'published' } })
    const wrapper = mountForm()

    await pickSupport(wrapper, 'social')
    await pickSupport(wrapper, 'web')
    await wrapper.find('[data-testid="image-territoire-west_africa"]').trigger('click')
    await wrapper.find('[data-testid="image-duree-5"]').trigger('click')
    await submitForm(wrapper)
    await signWithCode(wrapper, '987654')

    expect(requestCodeMock).toHaveBeenCalledWith({ purpose: 'mission_publish' })
    expect(createMissionMock).toHaveBeenCalledTimes(1)
    expect(createMissionMock.mock.calls[0]![0]).toMatchObject({
      titre: 'Spot TV jus de bissap',
      budget: 50000,
      lieu: 'Cotonou',
      image_terms: {
        finalite: 'Publicité',
        supports: ['social', 'web'],
        territoire: 'west_africa',
        duree_annees: 5,
      },
      signature: { name: 'Jean Producteur', code: '987654' },
    })
    expect(wrapper.emitted('success')).toHaveLength(1)
    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
  })

  it('code refusé : la fenêtre reste ouverte avec l\'erreur, la mission n\'est pas publiée', async () => {
    requestCodeMock.mockResolvedValue({ data: { sent_to: 'j•••@gmail.com', expires_at: 'x', retry_after: 60 } })
    createMissionMock.mockResolvedValue({
      success: false,
      errorCode: 'SIGNATURE_CODE_INVALID',
      message: 'Code invalide',
      errors: {},
    })
    const wrapper = mountForm()

    await openSignDialog(wrapper)
    await signWithCode(wrapper, '111111')

    expect(wrapper.find('[data-testid="image-contract-error"]').text()).toBe(
      "Ce code n'est pas le bon. Vérifiez-le et réessayez.",
    )
    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(true)
    expect(wrapper.emitted('success')).toBeFalsy()
  })

  it('erreur de champ de la mission : ferme la fenêtre et l\'affiche sur le formulaire', async () => {
    requestCodeMock.mockResolvedValue({ data: { sent_to: 'j•••@gmail.com', expires_at: 'x', retry_after: 60 } })
    createMissionMock.mockResolvedValue({
      success: false,
      errorCode: 'VALIDATION_ERROR',
      message: 'Invalide',
      errors: { titre: ['Titre refusé par le serveur'] },
    })
    const wrapper = mountForm()

    await openSignDialog(wrapper)
    await signWithCode(wrapper, '123456')

    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Titre refusé par le serveur')
  })

  it('nom de signature refusé (signature.name) : fenêtre ouverte, message dans la fenêtre, retour possible au nom', async () => {
    requestCodeMock.mockResolvedValue({ data: { sent_to: 'j•••@gmail.com', expires_at: 'x', retry_after: 60 } })
    createMissionMock.mockResolvedValue({
      success: false,
      errorCode: 'VALIDATION_ERROR',
      message: 'Invalide',
      errors: { 'signature.name': ['Le nom ne doit pas contenir de numéro ni de lien.'] },
    })
    const wrapper = mountForm()

    await openSignDialog(wrapper)
    await signWithCode(wrapper, '123456')

    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="image-contract-error"]').text()).toBe(
      'Le nom ne doit pas contenir de numéro ni de lien.',
    )
    // Pas de message de formulaire périmé.
    expect(wrapper.find('[data-testid="image-terms-error"]').exists()).toBe(false)

    await wrapper.get('[data-testid="image-contract-edit-name"]').trigger('click')
    expect(wrapper.find('[data-testid="image-contract-send"]').exists()).toBe(true)
    expect((wrapper.get('[data-testid="image-contract-name"]').element as HTMLInputElement).value).toBe('Jean Producteur')
    expect(wrapper.find('[data-testid="image-contract-error"]').exists()).toBe(false)
  })

  it('autre clé signature.* : fenêtre ouverte avec le message, sans lien de nom', async () => {
    requestCodeMock.mockResolvedValue({ data: { sent_to: 'j•••@gmail.com', expires_at: 'x', retry_after: 60 } })
    createMissionMock.mockResolvedValue({
      success: false,
      errorCode: 'VALIDATION_ERROR',
      message: 'Invalide',
      errors: { 'signature.code': ['Le code doit contenir 6 chiffres.'] },
    })
    const wrapper = mountForm()

    await openSignDialog(wrapper)
    await signWithCode(wrapper, '123456')

    expect(wrapper.get('[data-testid="image-contract-error"]').text()).toBe('Le code doit contenir 6 chiffres.')
    expect(wrapper.find('[data-testid="image-contract-edit-name"]').exists()).toBe(false)
  })

  it('signature réclamée elle-même (clé « signature ») sans section : message de formulaire périmé', async () => {
    createMissionMock.mockResolvedValue({ success: false, errors: { signature: ['La signature est obligatoire.'] } })
    const wrapper = mountForm({ flag: false })
    const auth = useAuthStore()
    vi.mocked(auth.refreshUser).mockImplementation(async () => {
      auth.user = { ...(auth.user as object), image_contracts_enabled: true } as never
      return true
    })

    await submitForm(wrapper)
    await flushPromises()

    expect(wrapper.get('[data-testid="image-terms-error"]').text()).toBe(
      "Une nouvelle étape est nécessaire : renseignez l'utilisation de l'image.",
    )
  })

  it('erreur sur image_terms renvoyée par le serveur : fenêtre fermée, message sous la section', async () => {
    requestCodeMock.mockResolvedValue({ data: { sent_to: 'j•••@gmail.com', expires_at: 'x', retry_after: 60 } })
    createMissionMock.mockResolvedValue({
      success: false,
      errorCode: 'VALIDATION_ERROR',
      message: 'Invalide',
      errors: { 'image_terms.supports': ['Support invalide.'] },
    })
    const wrapper = mountForm()

    await openSignDialog(wrapper)
    await signWithCode(wrapper, '123456')

    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="image-terms-error"]').text()).toBe('Support invalide.')
  })

  it('mission UGC produit seul : même bouton, photos jointes avec image_terms et signature', async () => {
    requestCodeMock.mockResolvedValue({ data: { sent_to: 'j•••@gmail.com', expires_at: 'x', retry_after: 60 } })
    createMissionMock.mockResolvedValue({ success: true, data: { id: 'm-2', status: 'pending_payment' } })
    const wrapper = mountForm({ initialValues: { ...standardValues(), type_mission: 'ugc' } })
    await flushPromises()

    await wrapper.find('#nom_produit').setValue('Sérum éclat')
    await wrapper.find('#valeur_produit').setValue('25000')
    const input = wrapper.find('[data-testid="product-photos-input"]')
    const photo = new File(['x'], 'produit.jpg', { type: 'image/jpeg' })
    Object.defineProperty(photo, 'size', { value: 1024 })
    Object.defineProperty(input.element, 'files', { value: [photo], writable: true })
    await input.trigger('change')
    await flushPromises()

    expect(wrapper.find('[data-testid="submit-button"]').text()).toContain('Lire, signer et publier')
    expect((wrapper.find('[data-testid="image-finalite"]').element as HTMLInputElement).value).toBe(
      'Vidéos de présentation du produit (UGC)',
    )

    await openSignDialog(wrapper)
    await signWithCode(wrapper, '654321')

    const payload = createMissionMock.mock.calls[0]![0]
    expect(payload.type_mission).toBe('ugc')
    expect(payload.product_photos).toHaveLength(1)
    expect(payload.image_terms.supports).toEqual(['social'])
    expect(payload.signature).toEqual({ name: 'Jean Producteur', code: '654321' })
  })

  it('type de mission modifiable : la finalité suit tant qu\'elle n\'est pas saisie', async () => {
    const wrapper = mountForm()

    await setSelectValue(wrapper, '#type_mission', 'film')
    await flushPromises()

    expect((wrapper.find('[data-testid="image-finalite"]').element as HTMLInputElement).value).toBe('Film')
  })
})
