import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { ref, nextTick } from 'vue'
import BookingFormSheet from '../BookingFormSheet.vue'
import { addDaysIso, bookingMinStartDateIso } from '@/lib/dates'
import { setSelectValue } from '@/components/ui/__tests__/selectTestUtils'

const { createBookingMock, requestCodeMock, authState } = vi.hoisted(() => ({
  createBookingMock: vi.fn(),
  requestCodeMock: vi.fn(),
  authState: {
    user: {
      userable_type: 'Producer',
      image_contracts_enabled: false,
      userable: { first_name: 'Jean', last_name: 'Producteur', display_name: 'Agence J' },
    } as Record<string, unknown>,
  },
}))

vi.mock('../../composables/useBookingCreate', () => ({
  useBookingCreate: () => ({
    createBooking: createBookingMock,
    isSubmitting: ref(false),
    error: ref(null),
    validationErrors: ref(null),
  }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn() }),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => authState,
}))

vi.mock('@/features/imageContract/services/imageContractApi', () => ({
  imageContractApi: { requestCode: requestCodeMock, getTemplate: vi.fn() },
}))

const FACE_UUID = '550e8400-e29b-41d4-a716-446655440000'

const mountForm = () =>
  mount(BookingFormSheet, {
    props: {
      isOpen: true,
      faceId: FACE_UUID,
      faceName: 'Jane Doe',
      tarifHoraire: 5000,
      tarifJournalier: 30000,
    },
    global: { stubs: { Teleport: { template: '<slot />' } } },
  })

const waitForValidation = async () => {
  await flushPromises()
  await nextTick()
  await new Promise((resolve) => setTimeout(resolve, 10))
  await flushPromises()
}

async function fillValidCashForm(wrapper: ReturnType<typeof mountForm>) {
  const day = addDaysIso(bookingMinStartDateIso(), 5)
  await wrapper.find('#date_debut').setValue(day)
  await wrapper.find('#date_fin').setValue(day)
  await setSelectValue(wrapper, '#lieu', 'Cotonou')
  await flushPromises()
}

async function pickSupport(wrapper: ReturnType<typeof mountForm>, value: string) {
  await wrapper.find(`[data-testid="image-support-${value}"]`).trigger('click')
}

async function submitForm(wrapper: ReturnType<typeof mountForm>) {
  await wrapper.find('form').trigger('submit')
  await waitForValidation()
}

function setFlag(enabled: boolean | undefined): void {
  authState.user = {
    userable_type: 'Producer',
    userable: { first_name: 'Jean', last_name: 'Producteur', display_name: 'Agence J' },
    ...(enabled === undefined ? {} : { image_contracts_enabled: enabled }),
  }
}

describe('BookingFormSheet : drapeau image_contracts_enabled faux', () => {
  beforeEach(() => {
    createBookingMock.mockReset()
    requestCodeMock.mockReset()
  })

  it.each([false, undefined])('rendu inchangé (drapeau %s) : ni section, ni encadré, ni fenêtre', (flag) => {
    setFlag(flag)
    const wrapper = mountForm()

    expect(wrapper.find('[data-testid="image-terms-section"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="image-contract-code-notice"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="submit-booking"]').text()).toContain('Envoyer la demande')
  })

  it('envoi inchangé : aucune clé image_terms ni signature', async () => {
    setFlag(false)
    createBookingMock.mockResolvedValue({ success: true, data: { id: 'abc' } })
    const wrapper = mountForm()

    await fillValidCashForm(wrapper)
    await submitForm(wrapper)

    expect(createBookingMock).toHaveBeenCalledTimes(1)
    const payload = createBookingMock.mock.calls[0][0]
    expect(payload).not.toHaveProperty('image_terms')
    expect(payload).not.toHaveProperty('signature')
    expect(wrapper.emitted('success')).toBeTruthy()
  })
})

describe('BookingFormSheet : drapeau image_contracts_enabled vrai', () => {
  beforeEach(() => {
    setFlag(true)
    createBookingMock.mockReset()
    requestCodeMock.mockReset()
  })

  it('affiche la section « Utilisation de l\'image » avec ses choix', () => {
    const wrapper = mountForm()

    const section = wrapper.find('[data-testid="image-terms-section"]')
    expect(section.text()).toContain("Utilisation de l'image")
    expect(section.find('[data-testid="image-terms-intro"]').text()).toBe(
      "La Face verra ces conditions avant d'accepter. Les droits démarrent une fois la Face payée.",
    )
    expect(wrapper.findAll('[data-testid^="image-support-"]').map((b) => b.text())).toEqual([
      'Réseaux sociaux', 'Site web', 'Télévision', 'Affichage', 'Cinéma', 'Tous supports',
    ])
    expect(wrapper.findAll('[data-testid^="image-territoire-"]').map((b) => b.text())).toEqual([
      'Bénin', "Afrique de l'Ouest", 'Monde entier',
    ])
    expect(wrapper.findAll('[data-testid^="image-duree-"]').map((b) => b.text())).toEqual(['1 an', '3 ans', '5 ans', '10 ans'])
    expect((wrapper.find('[data-testid="image-finalite"]').element as HTMLInputElement).value).toBe('Publicité')
  })

  it('affiche l\'encadré orange au-dessus du bouton « Lire et signer le contrat »', () => {
    const wrapper = mountForm()

    const notice = wrapper.find('[data-testid="image-contract-code-notice"]')
    expect(notice.text()).toContain('Un code vous sera envoyé par e-mail')
    expect(notice.text()).toContain('Vous devrez le saisir pour signer. Ouvrez votre boîte e-mail.')
    expect(notice.classes()).toEqual(expect.arrayContaining(['border-2', 'border-[#d97706]', 'bg-[#fef3c7]', 'text-[#78350f]']))
    expect(wrapper.find('[data-testid="submit-booking"]').text()).toContain('Lire et signer le contrat')

    const html = wrapper.html()
    expect(html.indexOf('image-contract-code-notice')).toBeLessThan(html.indexOf('submit-booking'))
  })

  it('« Tous supports » est exclusif', async () => {
    const wrapper = mountForm()

    await pickSupport(wrapper, 'social')
    await pickSupport(wrapper, 'web')
    expect(wrapper.find('[data-testid="image-support-social"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('[data-testid="image-support-web"]').attributes('aria-pressed')).toBe('true')

    await pickSupport(wrapper, 'all')
    expect(wrapper.find('[data-testid="image-support-all"]').attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('[data-testid="image-support-social"]').attributes('aria-pressed')).toBe('false')
    expect(wrapper.find('[data-testid="image-support-web"]').attributes('aria-pressed')).toBe('false')

    await pickSupport(wrapper, 'tv')
    expect(wrapper.find('[data-testid="image-support-all"]').attributes('aria-pressed')).toBe('false')
    expect(wrapper.find('[data-testid="image-support-tv"]').attributes('aria-pressed')).toBe('true')
  })

  it('refuse d\'ouvrir la signature sans support choisi', async () => {
    const wrapper = mountForm()

    await fillValidCashForm(wrapper)
    await submitForm(wrapper)

    expect(wrapper.find('[data-testid="image-terms-error"]').text()).toBe('Choisissez au moins un support.')
    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
    expect(createBookingMock).not.toHaveBeenCalled()
  })

  it('refuse d\'ouvrir la signature avec une finalité vide', async () => {
    const wrapper = mountForm()

    await fillValidCashForm(wrapper)
    await pickSupport(wrapper, 'social')
    await wrapper.find('[data-testid="image-finalite"]').setValue('   ')
    await submitForm(wrapper)

    expect(wrapper.find('[data-testid="image-terms-error"]').text()).toContain('finalité')
    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
  })

  it('ouvre la fenêtre de signature sans créer le booking, nom prérempli', async () => {
    const wrapper = mountForm()

    await fillValidCashForm(wrapper)
    await pickSupport(wrapper, 'social')
    await submitForm(wrapper)

    expect(createBookingMock).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(true)
    expect((wrapper.find('[data-testid="image-contract-name"]').element as HTMLInputElement).value).toBe('Jean Producteur')
    expect(wrapper.find('[data-testid="summary-supports"]').text()).toBe('Réseaux sociaux')
    expect(wrapper.find('[data-testid="summary-territoire"]').text()).toBe('Bénin')
    expect(wrapper.find('[data-testid="summary-duree"]').text()).toBe('1 an')
  })

  it('envoie le booking avec image_terms et signature après saisie du code', async () => {
    requestCodeMock.mockResolvedValue({ data: { sent_to: 'j•••@gmail.com', expires_at: 'x', retry_after: 60 } })
    createBookingMock.mockResolvedValue({ success: true, data: { id: 'abc' } })
    const wrapper = mountForm()

    await fillValidCashForm(wrapper)
    await pickSupport(wrapper, 'social')
    await pickSupport(wrapper, 'web')
    await wrapper.find('[data-testid="image-territoire-west_africa"]').trigger('click')
    await wrapper.find('[data-testid="image-duree-5"]').trigger('click')
    await submitForm(wrapper)

    await wrapper.find('[data-testid="image-contract-accept"]').setValue(true)
    await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
    await flushPromises()
    expect(requestCodeMock).toHaveBeenCalledWith({ purpose: 'booking_request' })

    const paste = new Event('paste', { bubbles: true, cancelable: true }) as Event & { clipboardData: unknown }
    paste.clipboardData = { getData: () => '987654' }
    wrapper.find('[data-testid="code-digit-0"]').element.dispatchEvent(paste)
    await flushPromises()
    await wrapper.find('[data-testid="image-contract-confirm"]').trigger('click')
    await flushPromises()

    expect(createBookingMock).toHaveBeenCalledTimes(1)
    expect(createBookingMock.mock.calls[0][0]).toMatchObject({
      face_id: FACE_UUID,
      type_contenu: 'Publicité',
      lieu: 'Cotonou',
      image_terms: {
        finalite: 'Publicité',
        supports: ['social', 'web'],
        territoire: 'west_africa',
        duree_annees: 5,
      },
      signature: { name: 'Jean Producteur', code: '987654' },
    })
    expect(wrapper.emitted('success')).toBeTruthy()
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('code refusé : la fenêtre reste ouverte avec l\'erreur, le booking n\'est pas créé', async () => {
    requestCodeMock.mockResolvedValue({ data: { sent_to: 'j•••@gmail.com', expires_at: 'x', retry_after: 60 } })
    createBookingMock.mockResolvedValue({
      success: false,
      errorCode: 'SIGNATURE_CODE_INVALID',
      message: 'Code invalide',
      errors: {},
    })
    const wrapper = mountForm()

    await fillValidCashForm(wrapper)
    await pickSupport(wrapper, 'cinema')
    await submitForm(wrapper)
    await wrapper.find('[data-testid="image-contract-accept"]').setValue(true)
    await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
    await flushPromises()
    const paste = new Event('paste', { bubbles: true, cancelable: true }) as Event & { clipboardData: unknown }
    paste.clipboardData = { getData: () => '111111' }
    wrapper.find('[data-testid="code-digit-0"]').element.dispatchEvent(paste)
    await flushPromises()
    await wrapper.find('[data-testid="image-contract-confirm"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="image-contract-error"]').text()).toBe(
      "Ce code n'est pas le bon. Vérifiez-le et réessayez.",
    )
    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(true)
    expect(wrapper.emitted('success')).toBeFalsy()
  })

  it('erreur de champ du booking : ferme la fenêtre et l\'affiche sur le formulaire', async () => {
    requestCodeMock.mockResolvedValue({ data: { sent_to: 'j•••@gmail.com', expires_at: 'x', retry_after: 60 } })
    createBookingMock.mockResolvedValue({
      success: false,
      errorCode: 'VALIDATION_ERROR',
      message: 'Invalide',
      errors: { date_debut: ['Date refusée par le serveur'] },
    })
    const wrapper = mountForm()

    await fillValidCashForm(wrapper)
    await pickSupport(wrapper, 'social')
    await submitForm(wrapper)
    await wrapper.find('[data-testid="image-contract-accept"]').setValue(true)
    await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
    await flushPromises()
    const paste = new Event('paste', { bubbles: true, cancelable: true }) as Event & { clipboardData: unknown }
    paste.clipboardData = { getData: () => '123456' }
    wrapper.find('[data-testid="code-digit-0"]').element.dispatchEvent(paste)
    await flushPromises()
    await wrapper.find('[data-testid="image-contract-confirm"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Date refusée par le serveur')
  })
})
