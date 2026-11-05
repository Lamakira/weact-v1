import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { ref } from 'vue'
import FaceMissionDetailPage from '../FaceMissionDetailPage.vue'
import type { Mission, MissionCandidature } from '@/features/mission/types'
import type { ImageContract, MissionImageTerms } from '@/features/imageContract/types'

/**
 * FaceMissionDetailPage × contrat d'image des missions (incrément 2, côté Face).
 * Modèle : FaceBookingDetailPage.imageContract.spec.ts et FaceMissionDetailPage.spec.ts.
 */
const { requestCodeMock, mockToast } = vi.hoisted(() => ({
  requestCodeMock: vi.fn(),
  mockToast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
}))

vi.mock('@/composables/useToast', () => ({ useToast: () => mockToast }))
vi.mock('@/features/auth/services/authApi', () => ({ authApi: { resendVerificationEmail: vi.fn() } }))
vi.mock('@/features/imageContract/services/imageContractApi', () => ({
  imageContractApi: { requestCode: requestCodeMock, getTemplate: vi.fn(), downloadCandidaturePdf: vi.fn() },
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: 'mission-uuid-1' }, fullPath: '/face/missions/mission-uuid-1' }),
  useRouter: () => ({ push: vi.fn(), back: vi.fn(), replace: vi.fn() }),
  RouterLink: { props: ['to'], template: '<a><slot /></a>' },
}))

vi.mock('@/features/face/composables/useSubscriptionStatus', () => ({
  useSubscriptionStatus: () => ({ data: ref(null), tier: ref('pro'), fetchStatus: vi.fn() }),
}))

const mockMission = ref<Mission | null>(null)
const mockCandidature = ref<MissionCandidature | null>(null)
const mockFetchMission = vi.fn()

vi.mock('@/features/mission/composables', () => ({
  useMissionDetail: () => ({
    mission: mockMission,
    candidature: mockCandidature,
    isLoading: ref(false),
    error: ref(null),
    notFound: ref(false),
    ugcPaywall: ref(false),
    ugcPaywallMessage: ref(null),
    fetchMission: mockFetchMission,
    setCandidature: vi.fn(),
  }),
}))

const mockConfirmCandidature = vi.fn()
const mockConfirmError = ref<string | null>(null)
const mockConfirmErrorCode = ref<string | null>(null)
const mockReconfirmCandidature = vi.fn()
const mockReconfirmError = ref<string | null>(null)
const mockReconfirmErrorCode = ref<string | null>(null)

vi.mock('@/features/candidature/composables', () => ({
  useConfirmCandidature: () => ({
    isConfirming: ref(false),
    error: mockConfirmError,
    errorCode: mockConfirmErrorCode,
    confirmCandidature: mockConfirmCandidature,
  }),
  useReconfirmCandidature: () => ({
    isReconfirming: ref(false),
    error: mockReconfirmError,
    errorCode: mockReconfirmErrorCode,
    reconfirmCandidature: mockReconfirmCandidature,
  }),
  useCancelCandidature: () => ({
    isCancelling: ref(false),
    error: ref(null),
    successMessage: ref(null),
    cancelCandidature: vi.fn(),
    reset: vi.fn(),
  }),
}))

vi.mock('@/composables/useUgcShipment', () => ({
  useUgcShipment: () => ({
    isSubmitting: ref(false),
    error: ref(null),
    errorCode: ref(null),
    confirmShipment: vi.fn(),
    confirmReceipt: vi.fn(),
    clearError: vi.fn(),
  }),
}))

vi.mock('@/composables/useUgcDeliverable', () => ({
  useUgcDeliverable: () => ({
    isUploading: ref(false),
    uploadProgress: ref(null),
    error: ref(null),
    errorCode: ref(null),
    uploadDeliverable: vi.fn(),
    validateFile: vi.fn(() => ({ valid: true })),
    clearError: vi.fn(),
  }),
}))

const terms = (overrides: Partial<MissionImageTerms> = {}): MissionImageTerms => ({
  finalite: 'Publicité pour la marque Shade',
  supports: [{ value: 'social', label: 'Réseaux sociaux' }, { value: 'web', label: 'Site web' }],
  territoire: { value: 'west_africa', label: "Afrique de l'Ouest" },
  duree_annees: 3,
  producer_signed_at: '2026-11-04T10:00:00Z',
  producer_signed_name: 'Jean Producteur',
  ...overrides,
})

function createMission(overrides: Partial<Mission> = {}): Mission {
  return {
    id: 'mission-uuid-1',
    titre: 'Spot TV jus de bissap',
    description: 'Description de la mission',
    date_tournage: '2026-12-15',
    profil_recherche: 'Femme 25-35 ans',
    budget: 150000,
    date_limite_candidature: '2026-12-01',
    nombre_faces_voulu: 3,
    type_mission: 'publicite',
    type_mission_label: 'Publicité',
    type_mission_autre: null,
    type_compensation: null,
    type_compensation_label: null,
    nom_produit: null,
    valeur_produit: null,
    nombre_videos: null,
    montant_remuneration: null,
    commission_ugc: null,
    commission_paid_at: null,
    genre_voulu: 'tous',
    genre_voulu_label: 'Homme et Femme',
    lieu: 'Cotonou',
    duree: '2 jours',
    status: 'published',
    status_label: 'Publiée',
    is_accepting_candidatures: true,
    has_paid_payment: true,
    created_at: '2026-11-01T00:00:00Z',
    updated_at: '2026-11-01T00:00:00Z',
    image_terms: terms(),
    producer: {
      id: 'p-1', slug: 'agence', type: 'agency', agency_name: 'Agence', first_name: null, last_name: null,
      display_name: 'Agence', bio: null, profile_photo_url: null, thumbnail_url: null, agency_logo_url: null,
      agency_logo_thumbnail_url: null, average_rating: null, ratings_count: 0,
      created_at: '2026-01-01T00:00:00Z', updated_at: '2026-01-01T00:00:00Z',
    },
    ...overrides,
  } as Mission
}

function createUgcMission(overrides: Partial<Mission> = {}): Mission {
  return createMission({
    titre: 'Sneakers · Unbox + review',
    type_mission: 'autre' as Mission['type_mission'],
    type_mission_label: 'UGC',
    type_compensation: 'hybrid',
    type_compensation_label: 'Produit + Argent',
    nom_produit: 'Sneakers Shade Fit',
    valeur_produit: 35000,
    nombre_videos: 3,
    montant_remuneration: 20000,
    ...overrides,
  })
}

function candidature(overrides: Partial<MissionCandidature> = {}): MissionCandidature {
  return {
    id: 'cand-1',
    mission_id: 'mission-uuid-1',
    face_id: 'face-1',
    status: 'accepted',
    status_label: 'Acceptée',
    confirmation_deadline: new Date(Date.now() + 30 * 3600_000).toISOString(),
    reconfirm_deadline: new Date(Date.now() + 30 * 3600_000).toISOString(),
    montant_face_recoit: 135000,
    conversation_id: 'conv-1',
    message_motivation: null,
    created_at: '2026-11-02T00:00:00Z',
    updated_at: '2026-11-02T00:00:00Z',
    ...overrides,
  }
}

const signedContract = (): ImageContract => ({
  id: 'ic-1',
  status: 'signed',
  finalite: 'Publicité pour la marque Shade',
  supports: [{ value: 'social', label: 'Réseaux sociaux' }],
  territoire: { value: 'west_africa', label: "Afrique de l'Ouest" },
  duree_annees: 3,
  producer_signed_at: '2026-11-04T10:00:00Z',
  producer_signed_name: 'Jean Producteur',
  face_signed_at: '2026-11-05T10:00:00Z',
  face_signed_name: 'Aïcha Bello',
  guardian_name: null,
  effective_at: null,
  pdf_ready: true,
})

function mountPage() {
  return mount(FaceMissionDetailPage, {
    global: {
      plugins: [
        createTestingPinia({
          initialState: {
            auth: {
              user: {
                id: 1,
                email: 'aicha@test.com',
                email_verified: true,
                email_verified_at: '2026-01-01',
                userable_type: 'Face',
                userable: { sexe: 'femme', prenom: 'Aïcha', nom: 'Bello' },
              },
              token: 'fake-token',
            },
          },
          stubActions: false,
        }),
      ],
      stubs: {
        ApplyToMissionModal: true,
        RatingDisplay: true,
        Teleport: { template: '<slot />' },
        ConfirmModal: {
          name: 'ConfirmModal',
          props: ['isOpen', 'title', 'message', 'confirmText'],
          emits: ['confirm', 'cancel'],
          template: '<div v-if="isOpen" data-testid="confirm-modal-stub">{{ title }}</div>',
        },
        RouterLink: { template: '<a><slot /></a>', props: ['to'] },
      },
    },
  })
}

async function pasteCodeAndConfirm(wrapper: ReturnType<typeof mountPage>, code: string) {
  const paste = new Event('paste', { bubbles: true, cancelable: true }) as Event & { clipboardData: unknown }
  paste.clipboardData = { getData: () => code }
  wrapper.find('[data-testid="code-digit-0"]').element.dispatchEvent(paste)
  await flushPromises()
  await wrapper.find('[data-testid="image-contract-confirm"]').trigger('click')
  await flushPromises()
}

async function reachCodeStep(wrapper: ReturnType<typeof mountPage>) {
  await wrapper.get('[data-testid="hub-action-desktop"]').trigger('click')
  await wrapper.find('[data-testid="image-contract-accept"]').setValue(true)
  await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
  await flushPromises()
}

describe('FaceMissionDetailPage : contrat d\'image des missions', () => {
  beforeEach(() => {
    mockMission.value = null
    mockCandidature.value = null
    mockFetchMission.mockClear()
    mockConfirmCandidature.mockReset()
    mockReconfirmCandidature.mockReset()
    mockConfirmError.value = null
    mockConfirmErrorCode.value = null
    mockReconfirmError.value = null
    mockReconfirmErrorCode.value = null
    requestCodeMock.mockReset()
    mockToast.success.mockClear()
    mockToast.error.mockClear()
    requestCodeMock.mockResolvedValue({ data: { sent_to: 'a•••@test.com', expires_at: 'x', retry_after: 60 } })
  })

  describe('carte « Utilisation de votre image »', () => {
    it('avant de candidater : conditions et phrase, mission classique', async () => {
      mockMission.value = createMission()

      const wrapper = mountPage()
      await flushPromises()

      const card = wrapper.get('[data-testid="mission-image-terms-card"]')
      expect(card.text()).toContain('Utilisation de votre image')
      expect(card.get('[data-testid="image-terms-finalite"]').text()).toBe('Publicité pour la marque Shade')
      expect(card.get('[data-testid="image-terms-supports"]').text()).toBe('Réseaux sociaux, Site web')
      expect(card.get('[data-testid="image-terms-territoire"]').text()).toBe("Afrique de l'Ouest")
      expect(card.get('[data-testid="image-terms-duree"]').text()).toBe('3 ans')
      expect(card.get('[data-testid="mission-image-terms-note"]').text()).toBe(
        'Si vous êtes retenue, vous signerez ces conditions avec un code reçu par e-mail. Candidater ne vous engage pas encore.',
      )
    })

    it('avant de candidater : aussi sur une mission UGC', async () => {
      mockMission.value = createUgcMission()

      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="mission-image-terms-card"]').exists()).toBe(true)
    })

    it('absente quand la mission n\'a pas de conditions', async () => {
      mockMission.value = createMission({ image_terms: null })

      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="mission-image-terms-card"]').exists()).toBe(false)
    })

    it('après candidature (en attente) : toujours affichée sur le hub', async () => {
      mockMission.value = createMission()
      mockCandidature.value = candidature({ status: 'pending', status_label: 'En attente' })

      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="mission-image-terms-card"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="mission-image-terms-note"]').text()).toContain('Si vous êtes retenue')
    })

    it('retenue, contrat à signer : la phrase annonce la signature', async () => {
      mockMission.value = createMission()
      mockCandidature.value = candidature()

      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="mission-image-terms-note"]').text()).toContain('Vous êtes retenue')
    })

    it('non retenue : pas de conditions à signer', async () => {
      mockMission.value = createMission()
      mockCandidature.value = candidature({ status: 'rejected', status_label: 'Refusée' })

      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="mission-image-terms-card"]').exists()).toBe(false)
    })
  })

  describe('carte du contrat signé', () => {
    it('affiche le contrat à la place des conditions, sans demander de signer', async () => {
      mockMission.value = createMission()
      mockCandidature.value = candidature({ status: 'confirmed', status_label: 'Confirmée', image_contract: signedContract() })

      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="mission-image-terms-card"]').exists()).toBe(false)
      const card = wrapper.get('[data-testid="image-contract-card"]')
      expect(card.get('[data-testid="contract-face-signature"]').text()).toContain('Aïcha Bello')
      expect(card.find('[data-testid="contract-download"]').exists()).toBe(true)
    })

    it('indication de prise d\'effet : paiement en hybride, réception en produit seul', async () => {
      mockMission.value = createUgcMission()
      mockCandidature.value = candidature({ status: 'confirmed', image_contract: signedContract() })
      let wrapper = mountPage()
      await flushPromises()
      expect(wrapper.get('[data-testid="contract-usage-hint"]').text()).toBe('Elle devient possible après votre paiement.')

      mockMission.value = createUgcMission({ type_compensation: 'product', montant_remuneration: null })
      wrapper = mountPage()
      await flushPromises()
      expect(wrapper.get('[data-testid="contract-usage-hint"]').text()).toBe('Elle devient possible à la réception du produit.')
    })

    it('fenêtre de signature en produit seul : ligne protégée sur la réception', async () => {
      mockMission.value = createUgcMission({ type_compensation: 'product', montant_remuneration: null })
      mockCandidature.value = candidature()
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.get('[data-testid="hub-action-desktop"]').trigger('click')

      expect(wrapper.get('[data-testid="image-contract-protected"]').text()).toContain(
        'avant la réception du produit',
      )
    })

    it('mission sans conditions : aucune carte de contrat', async () => {
      mockMission.value = createMission({ image_terms: null })
      mockCandidature.value = candidature({ status: 'confirmed', status_label: 'Confirmée' })

      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.find('[data-testid="image-contract-card"]').exists()).toBe(false)
    })
  })

  describe('confirmation avec signature (mission classique)', () => {
    beforeEach(() => {
      mockMission.value = createMission()
      mockCandidature.value = candidature()
    })

    it('l\'action devient « Lire, signer et confirmer » avec l\'encadré du code au-dessus', async () => {
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.get('[data-testid="hub-action-desktop"]').text()).toContain('Lire, signer et confirmer')
      expect(wrapper.get('[data-testid="hub-action-mobile"]').text()).toContain('Lire, signer et confirmer')
      expect(wrapper.get('[data-testid="image-contract-code-notice"]').text()).toContain(
        'Un code vous sera envoyé par e-mail',
      )
      const html = wrapper.html()
      expect(html.indexOf('image-contract-code-notice')).toBeLessThan(html.indexOf('hub-steps'))
    })

    it('ouvre la fenêtre de signature au lieu de ConfirmModal, avec résumé et nom préremplis', async () => {
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.get('[data-testid="hub-action-desktop"]').trigger('click')

      expect(wrapper.find('[data-testid="confirm-modal-stub"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(true)
      expect((wrapper.get('[data-testid="image-contract-name"]').element as HTMLInputElement).value).toBe('Aïcha Bello')
      expect(wrapper.get('[data-testid="summary-projet"]').text()).toBe('Spot TV jus de bissap')
      expect(wrapper.get('[data-testid="summary-finalite"]').text()).toBe('Publicité pour la marque Shade')
      expect(wrapper.get('[data-testid="summary-contrepartie"]').text()).toContain('net')
      expect(wrapper.get('[data-testid="summary-contrepartie"]').text().replace(/\s/g, '')).toContain('135000')
      expect(mockConfirmCandidature).not.toHaveBeenCalled()
    })

    it('signe puis confirme : motif candidature_confirm, signature transmise, toast, relecture silencieuse', async () => {
      mockConfirmCandidature.mockResolvedValueOnce({ message: 'Participation confirmée' })
      const wrapper = mountPage()
      await flushPromises()
      mockFetchMission.mockClear()

      await reachCodeStep(wrapper)
      expect(requestCodeMock).toHaveBeenCalledWith({ purpose: 'candidature_confirm', candidature_id: 'cand-1' })
      expect(wrapper.get('[data-testid="image-contract-confirm"]').text()).toBe('Signer et confirmer')
      await pasteCodeAndConfirm(wrapper, '482913')

      expect(mockConfirmCandidature).toHaveBeenCalledWith('cand-1', { name: 'Aïcha Bello', code: '482913' })
      expect(mockToast.success).toHaveBeenCalledWith('Participation confirmée. Le chat est ouvert.')
      expect(mockFetchMission).toHaveBeenCalledWith('mission-uuid-1', { silent: true })
      expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
    })

    it('code refusé : la fenêtre garde son état avec le message, pas de toast ni de relecture', async () => {
      mockConfirmCandidature.mockImplementationOnce(async () => {
        mockConfirmError.value = 'Code invalide'
        mockConfirmErrorCode.value = 'SIGNATURE_CODE_INVALID'
        return null
      })
      const wrapper = mountPage()
      await flushPromises()
      mockFetchMission.mockClear()

      await reachCodeStep(wrapper)
      await pasteCodeAndConfirm(wrapper, '111111')

      expect(wrapper.get('[data-testid="image-contract-error"]').text()).toBe(
        "Ce code n'est pas le bon. Vérifiez-le et réessayez.",
      )
      expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(true)
      expect(mockToast.success).not.toHaveBeenCalled()
      expect(mockToast.error).not.toHaveBeenCalled()
      expect(mockFetchMission).not.toHaveBeenCalled()
    })

    it('date de naissance manquante : message avec lien vers le profil dans la fenêtre', async () => {
      mockConfirmCandidature.mockImplementationOnce(async () => {
        mockConfirmError.value = 'Date de naissance requise'
        mockConfirmErrorCode.value = 'BIRTH_DATE_REQUIRED'
        return null
      })
      const wrapper = mountPage()
      await flushPromises()

      await reachCodeStep(wrapper)
      await pasteCodeAndConfirm(wrapper, '222222')

      expect(wrapper.get('[data-testid="image-contract-error"]').text()).toContain('votre profil')
      expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(true)
    })

    it('autre échec (état changé) : message dans la fenêtre et relecture silencieuse', async () => {
      mockConfirmCandidature.mockImplementationOnce(async () => {
        mockConfirmError.value = 'Cette candidature ne peut pas être confirmée'
        mockConfirmErrorCode.value = 'INVALID_STATUS'
        return null
      })
      const wrapper = mountPage()
      await flushPromises()
      mockFetchMission.mockClear()

      await reachCodeStep(wrapper)
      await pasteCodeAndConfirm(wrapper, '333333')

      expect(wrapper.get('[data-testid="image-contract-error"]').text()).toBe('Cette candidature ne peut pas être confirmée')
      expect(mockFetchMission).toHaveBeenCalledWith('mission-uuid-1', { silent: true })
    })
  })

  describe('Face mineure', () => {
    it('passe par l\'étape parent et envoie le code au parent', async () => {
      mockMission.value = createMission({ image_terms: terms({ requires_guardian: true }) })
      mockCandidature.value = candidature()
      const wrapper = mountPage()
      await flushPromises()

      // L'encadré de la page annonce déjà que le code part au parent.
      expect(wrapper.get('[data-testid="image-contract-code-notice-title"]').text()).toBe(
        'Le code part à votre parent ou tuteur',
      )

      await wrapper.get('[data-testid="hub-action-desktop"]').trigger('click')
      await wrapper.find('[data-testid="image-contract-accept"]').setValue(true)
      await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
      expect(requestCodeMock).not.toHaveBeenCalled()

      await wrapper.find('[data-testid="guardian-name"]').setValue('Marie Dossou')
      await wrapper.find('[data-testid="guardian-relation-mere"]').trigger('click')
      await wrapper.find('[data-testid="guardian-email"]').setValue('marie@test.com')
      await wrapper.find('[data-testid="guardian-send"]').trigger('click')
      await flushPromises()

      expect(requestCodeMock).toHaveBeenCalledWith({
        purpose: 'candidature_confirm',
        candidature_id: 'cand-1',
        guardian_name: 'Marie Dossou',
        guardian_relation: 'mere',
        guardian_email: 'marie@test.com',
      })
    })

    it('refuse l\'e-mail du compte pour le parent', async () => {
      mockMission.value = createMission({ image_terms: terms({ requires_guardian: true }) })
      mockCandidature.value = candidature()
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.get('[data-testid="hub-action-desktop"]').trigger('click')
      await wrapper.find('[data-testid="image-contract-accept"]').setValue(true)
      await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
      await wrapper.find('[data-testid="guardian-email"]').setValue('aicha@test.com')

      expect(wrapper.get('[data-testid="guardian-email-error"]').text()).toContain('pas le vôtre')
    })
  })

  describe('reconfirmation UGC avec signature', () => {
    it('signe pour reconfirmer : reconfirmCandidature reçoit la signature', async () => {
      mockMission.value = createUgcMission()
      mockCandidature.value = candidature()
      mockReconfirmCandidature.mockResolvedValueOnce({ message: 'Participation reconfirmée' })
      const wrapper = mountPage()
      await flushPromises()
      mockFetchMission.mockClear()

      expect(wrapper.get('[data-testid="hub-action-desktop"]').text()).toContain('Lire, signer et confirmer')
      await reachCodeStep(wrapper)
      await pasteCodeAndConfirm(wrapper, '654321')

      expect(mockReconfirmCandidature).toHaveBeenCalledWith('cand-1', { name: 'Aïcha Bello', code: '654321' })
      expect(mockConfirmCandidature).not.toHaveBeenCalled()
      expect(mockToast.success).toHaveBeenCalledWith('Participation reconfirmée — le producteur va expédier votre produit')
      expect(mockFetchMission).toHaveBeenCalledWith('mission-uuid-1', { silent: true })
    })

    it('résumé : produit, valeur et cash net en hybride', async () => {
      mockMission.value = createUgcMission()
      mockCandidature.value = candidature({ montant_face_recoit: 18000 })
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.get('[data-testid="hub-action-desktop"]').trigger('click')

      const text = wrapper.get('[data-testid="summary-contrepartie"]').text().replace(/\s/g, '')
      expect(text).toContain('SneakersShadeFit')
      expect(text).toContain('35000')
      expect(text).toContain('18000')
      expect(text).toContain('net')
    })

    it('résumé : produit et valeur seulement en produit seul', async () => {
      mockMission.value = createUgcMission({ type_compensation: 'product', montant_remuneration: null })
      mockCandidature.value = candidature({ montant_face_recoit: null })
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.get('[data-testid="hub-action-desktop"]').trigger('click')

      const text = wrapper.get('[data-testid="summary-contrepartie"]').text().replace(/\s/g, '')
      expect(text).toContain('SneakersShadeFit')
      expect(text).toContain('35000')
      expect(text).not.toContain('net')
    })
  })

  describe('comportement inchangé sans signature à faire', () => {
    it('mission sans conditions : libellé d\'origine, ConfirmModal, confirmCandidature(id) seul', async () => {
      mockMission.value = createMission({ image_terms: null })
      mockCandidature.value = candidature()
      mockConfirmCandidature.mockResolvedValueOnce({ message: 'ok' })
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.get('[data-testid="hub-action-desktop"]').text()).toContain('Confirmer ma participation')
      expect(wrapper.find('[data-testid="image-contract-code-notice"]').exists()).toBe(false)

      await wrapper.get('[data-testid="hub-action-desktop"]').trigger('click')
      expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="confirm-modal-stub"]').exists()).toBe(true)

      wrapper.findComponent({ name: 'ConfirmModal' }).vm.$emit('confirm')
      await flushPromises()
      expect(mockConfirmCandidature).toHaveBeenCalledWith('cand-1')
    })

    it('mission à conditions mais contrat déjà signé : pas de nouvelle signature', async () => {
      mockMission.value = createMission()
      mockCandidature.value = candidature({ image_contract: signedContract() })
      const wrapper = mountPage()
      await flushPromises()

      expect(wrapper.get('[data-testid="hub-action-desktop"]').text()).toContain('Confirmer ma participation')
      await wrapper.get('[data-testid="hub-action-desktop"]').trigger('click')
      expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
      expect(wrapper.find('[data-testid="confirm-modal-stub"]').exists()).toBe(true)
    })

    it('mission UGC sans conditions : la reconfirmation reste directe', async () => {
      mockMission.value = createUgcMission({ image_terms: null })
      mockCandidature.value = candidature()
      mockReconfirmCandidature.mockResolvedValueOnce({ message: 'ok' })
      const wrapper = mountPage()
      await flushPromises()

      await wrapper.get('[data-testid="hub-action-desktop"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
      expect(mockReconfirmCandidature).toHaveBeenCalledWith('cand-1')
    })
  })
})
