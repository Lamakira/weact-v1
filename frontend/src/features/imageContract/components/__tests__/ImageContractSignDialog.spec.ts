import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { AxiosError, type AxiosResponse } from 'axios'
import ImageContractSignDialog from '../ImageContractSignDialog.vue'
import type { ContractSummary, SignatureSubmitOutcome } from '../../types'

const { requestCodeMock, getTemplateMock } = vi.hoisted(() => ({
  requestCodeMock: vi.fn(),
  getTemplateMock: vi.fn(),
}))

vi.mock('../../services/imageContractApi', () => ({
  imageContractApi: {
    requestCode: requestCodeMock,
    getTemplate: getTemplateMock,
  },
}))

const summary: ContractSummary = {
  projet: 'Publicité',
  finalite: 'Publicité pour la marque Shade',
  supports: ['Réseaux sociaux', 'Site web'],
  territoire: 'Bénin',
  dureeAnnees: 3,
  contrepartie: '42 500 FCFA net',
}

function apiError(status: number, code: string, message = 'Erreur', extra: Record<string, unknown> = {}): AxiosError {
  return new AxiosError(message, 'ERR_BAD_REQUEST', undefined, undefined, {
    status,
    data: { error: { code, message, ...extra } },
    statusText: '',
    headers: {},
    config: {} as AxiosResponse['config'],
  } as AxiosResponse)
}

function codeReply(sentTo = 'a•••••@gmail.com', retryAfter = 60) {
  return { data: { sent_to: sentTo, expires_at: '2026-11-04T10:10:00Z', retry_after: retryAfter } }
}

type Props = Partial<InstanceType<typeof ImageContractSignDialog>['$props']>

function mountDialog(props: Props = {}, submit?: (s: { name: string; code: string }) => Promise<SignatureSubmitOutcome>) {
  return mount(ImageContractSignDialog, {
    props: {
      isOpen: true,
      role: 'producer',
      summary,
      defaultName: 'Jean Producteur',
      submit: submit ?? vi.fn().mockResolvedValue({ ok: true }),
      ...props,
    } as never,
    global: {
      stubs: {
        Teleport: { template: '<slot />' },
        RouterLink: { props: ['to'], template: '<a :href="to" data-testid="profile-link"><slot /></a>' },
      },
    },
  })
}

function isDisabled(wrapper: VueWrapper, testid: string): boolean {
  return (wrapper.find(`[data-testid="${testid}"]`).element as HTMLButtonElement).disabled
}

async function accept(wrapper: VueWrapper): Promise<void> {
  await wrapper.find('[data-testid="image-contract-accept"]').setValue(true)
}

async function goToCodeStep(wrapper: VueWrapper): Promise<void> {
  await accept(wrapper)
  await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
  await flushPromises()
}

async function typeCode(wrapper: VueWrapper, code: string): Promise<void> {
  const clipboardData = { getData: () => code }
  const event = new Event('paste', { bubbles: true, cancelable: true }) as Event & { clipboardData: unknown }
  event.clipboardData = clipboardData
  wrapper.find('[data-testid="code-digit-0"]').element.dispatchEvent(event)
  await flushPromises()
}

describe('ImageContractSignDialog', () => {
  beforeEach(() => {
    requestCodeMock.mockReset()
    getTemplateMock.mockReset()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  describe('étape 1', () => {
    it('affiche les conditions particulières et le nom prérempli', () => {
      const wrapper = mountDialog()

      expect(wrapper.find('[data-testid="image-contract-title"]').text()).toBe("Votre contrat d'image")
      expect(wrapper.find('[data-testid="summary-projet"]').text()).toBe('Publicité')
      expect(wrapper.find('[data-testid="summary-finalite"]').text()).toBe('Publicité pour la marque Shade')
      expect(wrapper.find('[data-testid="summary-supports"]').text()).toBe('Réseaux sociaux, Site web')
      expect(wrapper.find('[data-testid="summary-territoire"]').text()).toBe('Bénin')
      expect(wrapper.find('[data-testid="summary-duree"]').text()).toBe('3 ans')
      expect(wrapper.find('[data-testid="summary-contrepartie"]').text()).toBe('42 500 FCFA net')
      expect((wrapper.find('[data-testid="image-contract-name"]').element as HTMLInputElement).value).toBe('Jean Producteur')
      expect(wrapper.find('[data-testid="image-contract-code-notice"]').exists()).toBe(true)
    })

    it('exige un nom d\'au moins 3 caractères', async () => {
      const wrapper = mountDialog()
      await accept(wrapper)
      expect(isDisabled(wrapper, 'image-contract-send')).toBe(false)

      await wrapper.find('[data-testid="image-contract-name"]').setValue('Jo')
      expect(isDisabled(wrapper, 'image-contract-send')).toBe(true)
      expect(wrapper.find('[data-testid="image-contract-name-error"]').exists()).toBe(true)

      await wrapper.find('[data-testid="image-contract-name"]').setValue('Joe')
      expect(isDisabled(wrapper, 'image-contract-send')).toBe(false)
    })

    it('exige la case « J\'ai lu le contrat »', async () => {
      const wrapper = mountDialog()

      expect(isDisabled(wrapper, 'image-contract-send')).toBe(true)
      await accept(wrapper)
      expect(isDisabled(wrapper, 'image-contract-send')).toBe(false)
      expect(wrapper.text()).toContain("J'ai lu le contrat et j'accepte de le signer.")
    })

    it('affiche « Ce qui reste protégé » pour la Face seulement', () => {
      expect(mountDialog({ role: 'producer' }).find('[data-testid="image-contract-protected"]').exists()).toBe(false)

      const face = mountDialog({ role: 'face', bookingId: 'b-1' })
      expect(face.find('[data-testid="image-contract-protected"]').text()).toContain('Ce qui reste protégé')
    })

    it('place le bouton « Lire le contrat complet » sous le résumé et avant « Ce qui reste protégé »', () => {
      const html = mountDialog({ role: 'face', bookingId: 'b-1' }).html()
      const summary = html.indexOf('data-testid="image-contract-summary"')
      const readFull = html.indexOf('data-testid="image-contract-read-full"')
      const protectedBlock = html.indexOf('data-testid="image-contract-protected"')

      expect(summary).toBeGreaterThan(-1)
      expect(readFull).toBeGreaterThan(summary)
      expect(protectedBlock).toBeGreaterThan(readFull)
    })

    it('expose aria-expanded et le libellé du bouton de lecture du contrat', async () => {
      getTemplateMock.mockResolvedValue({ version: 'v1', articles: [] })
      const wrapper = mountDialog()
      const button = wrapper.find('[data-testid="image-contract-read-full"]')

      expect(button.attributes('aria-expanded')).toBe('false')
      expect(button.text()).toBe('Lire le contrat complet')

      await button.trigger('click')
      await flushPromises()

      const after = wrapper.find('[data-testid="image-contract-read-full"]')
      expect(after.attributes('aria-expanded')).toBe('true')
      expect(after.text()).toBe('Masquer le contrat complet')
    })

    it('charge et affiche les articles via « Lire le contrat complet »', async () => {
      getTemplateMock.mockResolvedValue({
        version: 'v1',
        articles: [{ title: 'Objet', body: 'La Face autorise le Producteur.' }, { title: 'Usages exclus', body: 'Sauf accord.', bullets: ['Contenu politique'] }],
      })
      const wrapper = mountDialog()

      await wrapper.find('[data-testid="image-contract-read-full"]').trigger('click')
      await flushPromises()

      const articles = wrapper.find('[data-testid="image-contract-articles"]')
      expect(getTemplateMock).toHaveBeenCalledTimes(1)
      expect(articles.text()).toContain('Article 1. Objet')
      expect(articles.text()).toContain('La Face autorise le Producteur.')
      expect(articles.text()).toContain('Contenu politique')
    })

    it('signale un échec de chargement du contrat', async () => {
      getTemplateMock.mockRejectedValue(new Error('réseau'))
      const wrapper = mountDialog()

      await wrapper.find('[data-testid="image-contract-read-full"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('[data-testid="image-contract-articles"]').text()).toContain("n'a pas pu être chargé")
    })
  })

  describe('envoi et saisie du code', () => {
    it('envoie le code Producteur puis passe à l\'étape 2 avec l\'e-mail masqué', async () => {
      requestCodeMock.mockResolvedValue(codeReply())
      const wrapper = mountDialog()

      await goToCodeStep(wrapper)

      expect(requestCodeMock).toHaveBeenCalledWith({ purpose: 'booking_request' })
      expect(wrapper.find('[data-testid="image-contract-title"]').text()).toBe('Consultez votre e-mail')
      expect(wrapper.find('[data-testid="image-contract-sent-to"]').text()).toContain('a•••••@gmail.com')
      expect(wrapper.findAll('[data-testid^="code-digit-"]')).toHaveLength(6)
      expect(wrapper.find('[data-testid="image-contract-expiry"]').text()).toBe('Ce code expire dans 10 minutes.')
      expect(wrapper.find('[data-testid="image-contract-spam-hint"]').text()).toContain('spams')
    })

    it('envoie le code Face avec le booking à accepter', async () => {
      requestCodeMock.mockResolvedValue(codeReply())
      const wrapper = mountDialog({ role: 'face', bookingId: 'b-42' })

      await goToCodeStep(wrapper)

      expect(requestCodeMock).toHaveBeenCalledWith({ purpose: 'booking_accept', booking_id: 'b-42' })
    })

    it('gère le collage du code entier et active la confirmation', async () => {
      requestCodeMock.mockResolvedValue(codeReply())
      const wrapper = mountDialog()
      await goToCodeStep(wrapper)
      expect(isDisabled(wrapper, 'image-contract-confirm')).toBe(true)

      await typeCode(wrapper, '12 34-56')

      const values = wrapper.findAll('[data-testid^="code-digit-"]').map((input) => (input.element as HTMLInputElement).value)
      expect(values).toEqual(['1', '2', '3', '4', '5', '6'])
      expect(isDisabled(wrapper, 'image-contract-confirm')).toBe(false)
    })

    it('accepte la saisie chiffre par chiffre et ignore les caractères non numériques', async () => {
      requestCodeMock.mockResolvedValue(codeReply())
      const wrapper = mountDialog()
      await goToCodeStep(wrapper)

      const first = wrapper.find('[data-testid="code-digit-0"]')
      await first.setValue('a')
      expect((first.element as HTMLInputElement).value).toBe('')
      await first.setValue('7')
      expect((first.element as HTMLInputElement).value).toBe('7')
    })

    it('soumet nom et code, libellé Producteur', async () => {
      requestCodeMock.mockResolvedValue(codeReply())
      const submit = vi.fn().mockResolvedValue({ ok: true })
      const wrapper = mountDialog({}, submit)
      await goToCodeStep(wrapper)
      await typeCode(wrapper, '123456')

      const confirm = wrapper.find('[data-testid="image-contract-confirm"]')
      expect(confirm.text()).toContain('Signer et envoyer la demande')
      await confirm.trigger('click')
      await flushPromises()

      expect(submit).toHaveBeenCalledWith({ name: 'Jean Producteur', code: '123456' })
    })

    it('libellé Face et rappel « Sans ce code »', async () => {
      requestCodeMock.mockResolvedValue(codeReply())
      const wrapper = mountDialog({ role: 'face', bookingId: 'b-1' })
      await goToCodeStep(wrapper)

      expect(wrapper.find('[data-testid="image-contract-confirm"]').text()).toContain('Signer et accepter le booking')
      expect(wrapper.find('[data-testid="image-contract-no-code-hint"]').text()).toBe(
        'Sans ce code, le booking ne peut pas être accepté.',
      )
    })

    it('n\'affiche pas le rappel « Sans ce code » côté Producteur', async () => {
      requestCodeMock.mockResolvedValue(codeReply())
      const wrapper = mountDialog()
      await goToCodeStep(wrapper)

      expect(wrapper.find('[data-testid="image-contract-no-code-hint"]').exists()).toBe(false)
    })
  })

  describe('renvoi du code', () => {
    it('désactive « Renvoyer » pendant 60 s avec un décompte, puis le réactive', async () => {
      vi.useFakeTimers()
      requestCodeMock.mockResolvedValue(codeReply())
      const wrapper = mountDialog()
      await goToCodeStep(wrapper)

      const resendText = () => wrapper.find('[data-testid="image-contract-resend"]').text()
      expect(isDisabled(wrapper, 'image-contract-resend')).toBe(true)
      expect(resendText()).toBe('Renvoyer le code (60 s)')

      await vi.advanceTimersByTimeAsync(25_000)
      expect(resendText()).toBe('Renvoyer le code (35 s)')

      await vi.advanceTimersByTimeAsync(35_000)
      expect(isDisabled(wrapper, 'image-contract-resend')).toBe(false)
      expect(resendText()).toBe('Renvoyer le code')
    })

    it('renvoie un code, vide les cases et relance le décompte', async () => {
      vi.useFakeTimers()
      requestCodeMock.mockResolvedValue(codeReply())
      const wrapper = mountDialog()
      await goToCodeStep(wrapper)
      await typeCode(wrapper, '123456')
      await vi.advanceTimersByTimeAsync(60_000)

      await wrapper.find('[data-testid="image-contract-resend"]').trigger('click')
      await flushPromises()

      expect(requestCodeMock).toHaveBeenCalledTimes(2)
      expect((wrapper.find('[data-testid="code-digit-0"]').element as HTMLInputElement).value).toBe('')
      expect(wrapper.find('[data-testid="image-contract-resend"]').text()).toBe('Renvoyer le code (60 s)')
    })
  })

  describe('erreurs', () => {
    it.each([
      ['SIGNATURE_CODE_INVALID', "Ce code n'est pas le bon. Vérifiez-le et réessayez."],
      ['SIGNATURE_CODE_EXPIRED', 'Ce code a expiré. Demandez-en un nouveau.'],
      ['SIGNATURE_CODE_TOO_MANY_ATTEMPTS', 'Trop de tentatives. Demandez un nouveau code.'],
    ])('affiche en français l\'erreur %s à la soumission', async (code, message) => {
      requestCodeMock.mockResolvedValue(codeReply())
      const submit = vi.fn().mockResolvedValue({ ok: false, code, message: 'texte serveur' })
      const wrapper = mountDialog({}, submit)
      await goToCodeStep(wrapper)
      await typeCode(wrapper, '123456')

      await wrapper.find('[data-testid="image-contract-confirm"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('[data-testid="image-contract-error"]').text()).toBe(message)
      // Un code refusé ne reste pas saisi : il faut le retaper.
      expect((wrapper.find('[data-testid="code-digit-0"]').element as HTMLInputElement).value).toBe('')
    })

    it('SIGNATURE_CODE_TOO_SOON : message et décompte relancé avec retry_after', async () => {
      vi.useFakeTimers()
      requestCodeMock.mockRejectedValue(apiError(429, 'SIGNATURE_CODE_TOO_SOON', 'Trop tôt', { retry_after: 42 }))
      const wrapper = mountDialog()
      await accept(wrapper)

      await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('[data-testid="image-contract-error"]').text()).toContain('Patientez un instant')
      expect(wrapper.find('[data-testid="image-contract-title"]').text()).toBe("Votre contrat d'image")
    })

    it('SIGNATURE_CODE_SEND_FAILED : reste à l\'étape 1 avec le message', async () => {
      requestCodeMock.mockRejectedValue(apiError(503, 'SIGNATURE_CODE_SEND_FAILED', 'Echec'))
      const wrapper = mountDialog()
      await accept(wrapper)

      await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('[data-testid="image-contract-error"]').text()).toBe(
        "Nous n'avons pas pu envoyer l'e-mail. Réessayez dans un instant.",
      )
      expect(wrapper.find('[data-testid="code-digit-0"]').exists()).toBe(false)
    })

    it('BIRTH_DATE_REQUIRED : message avec lien vers le profil', async () => {
      requestCodeMock.mockRejectedValue(apiError(422, 'BIRTH_DATE_REQUIRED', 'Date de naissance requise'))
      const wrapper = mountDialog({ role: 'face', bookingId: 'b-1' })
      await accept(wrapper)

      await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
      await flushPromises()

      const error = wrapper.find('[data-testid="image-contract-error"]')
      expect(error.text()).toContain('date de naissance')
      expect(error.find('[data-testid="profile-link"]').attributes('href')).toBe('/face/profile')
    })

    it('affiche le message serveur pour un code d\'erreur inconnu', async () => {
      requestCodeMock.mockRejectedValue(apiError(422, 'AUTRE', 'Message du serveur'))
      const wrapper = mountDialog()
      await accept(wrapper)

      await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('[data-testid="image-contract-error"]').text()).toBe('Message du serveur')
    })
  })

  describe('étape mineure (Face)', () => {
    it('passe par l\'étape parent avant le code, sans appel API à l\'étape 1', async () => {
      const wrapper = mountDialog({ role: 'face', bookingId: 'b-1', requiresGuardian: true })
      await accept(wrapper)

      const next = wrapper.find('[data-testid="image-contract-send"]')
      expect(next.text()).toBe('Continuer')
      await next.trigger('click')
      await flushPromises()

      expect(requestCodeMock).not.toHaveBeenCalled()
      expect(wrapper.find('[data-testid="image-contract-title"]').text()).toBe('Un parent doit signer pour vous')
      expect(wrapper.find('[data-testid="image-contract-guardian-text"]').text()).toBe(
        'Vous avez moins de 18 ans : votre parent ou tuteur doit signer cette autorisation avec vous.',
      )
      expect(wrapper.find('[data-testid="image-contract-code-notice-title"]').text()).toBe(
        'Le code part à votre parent ou tuteur',
      )
      expect(wrapper.find('[data-testid="image-contract-code-notice-text"]').text()).toBe(
        'Il reçoit le contrat et le code par e-mail. Sans ce code, le booking ne peut pas être accepté.',
      )
      expect(wrapper.findAll('[data-testid^="guardian-relation-"]').map((b) => b.text())).toEqual(['Mère', 'Père', 'Tuteur'])
    })

    it('exige nom, lien et e-mail valides puis envoie le code au parent', async () => {
      requestCodeMock.mockResolvedValue(codeReply('m•••@gmail.com'))
      const wrapper = mountDialog({ role: 'face', bookingId: 'b-1', requiresGuardian: true })
      await accept(wrapper)
      await wrapper.find('[data-testid="image-contract-send"]').trigger('click')

      expect(wrapper.find('[data-testid="guardian-send"]').text()).toBe('Envoyer le code à mon parent')
      expect(isDisabled(wrapper, 'guardian-send')).toBe(true)

      await wrapper.find('[data-testid="guardian-name"]').setValue('Marie Dossou')
      await wrapper.find('[data-testid="guardian-relation-mere"]').trigger('click')
      await wrapper.find('[data-testid="guardian-email"]').setValue('pas-un-email')
      expect(isDisabled(wrapper, 'guardian-send')).toBe(true)

      await wrapper.find('[data-testid="guardian-email"]').setValue('marie@gmail.com')
      expect(isDisabled(wrapper, 'guardian-send')).toBe(false)

      await wrapper.find('[data-testid="guardian-send"]').trigger('click')
      await flushPromises()

      expect(requestCodeMock).toHaveBeenCalledWith({
        purpose: 'booking_accept',
        booking_id: 'b-1',
        guardian_name: 'Marie Dossou',
        guardian_relation: 'mere',
        guardian_email: 'marie@gmail.com',
      })
      expect(wrapper.find('[data-testid="image-contract-sent-to"]').text()).toContain('m•••@gmail.com')
    })

    async function openGuardianStep(accountEmail = 'aicha@gmail.com') {
      const wrapper = mountDialog({ role: 'face', bookingId: 'b-1', requiresGuardian: true, accountEmail })
      await accept(wrapper)
      await wrapper.find('[data-testid="image-contract-send"]').trigger('click')
      await wrapper.find('[data-testid="guardian-relation-mere"]').trigger('click')
      return wrapper
    }

    it('exige 3 caractères pour le nom du parent', async () => {
      const wrapper = await openGuardianStep()
      await wrapper.find('[data-testid="guardian-email"]').setValue('marie@gmail.com')

      await wrapper.find('[data-testid="guardian-name"]').setValue('Ma')
      expect(isDisabled(wrapper, 'guardian-send')).toBe(true)
      await wrapper.find('[data-testid="guardian-name"]').setValue('Mar')
      expect(isDisabled(wrapper, 'guardian-send')).toBe(false)
    })

    it('refuse l\'e-mail du compte (casse et espaces ignorés)', async () => {
      const wrapper = await openGuardianStep('Aicha@Gmail.com')
      await wrapper.find('[data-testid="guardian-name"]').setValue('Marie Dossou')
      await wrapper.find('[data-testid="guardian-email"]').setValue('  aicha@gmail.COM ')

      expect(isDisabled(wrapper, 'guardian-send')).toBe(true)
      expect(wrapper.find('[data-testid="guardian-email-error"]').text()).toBe(
        "Indiquez l'e-mail de votre parent ou tuteur, pas le vôtre.",
      )

      await wrapper.find('[data-testid="guardian-email"]').setValue('marie@gmail.com')
      expect(isDisabled(wrapper, 'guardian-send')).toBe(false)
      expect(wrapper.find('[data-testid="guardian-email-error"]').exists()).toBe(false)
    })

    it('affiche les erreurs 422 guardian_name et guardian_email dans l\'étape parent', async () => {
      const err = new AxiosError('x', 'ERR', undefined, undefined, {
        status: 422,
        data: { error: { code: 'VALIDATION_ERROR', message: 'Invalide', details: { guardian_name: ['Nom trop court.'], guardian_email: ['E-mail refusé.'] } } },
        statusText: '',
        headers: {},
        config: {} as AxiosResponse['config'],
      } as AxiosResponse)
      requestCodeMock.mockRejectedValue(err)
      const wrapper = await openGuardianStep()
      await wrapper.find('[data-testid="guardian-name"]').setValue('Marie Dossou')
      await wrapper.find('[data-testid="guardian-email"]').setValue('marie@gmail.com')

      await wrapper.find('[data-testid="guardian-send"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('[data-testid="guardian-name-error"]').text()).toBe('Nom trop court.')
      expect(wrapper.find('[data-testid="guardian-email-error"]').text()).toBe('E-mail refusé.')
      expect(wrapper.find('[data-testid="image-contract-title"]').text()).toBe('Un parent doit signer pour vous')
    })

    it('permet de revenir à l\'étape 1', async () => {
      const wrapper = mountDialog({ role: 'face', bookingId: 'b-1', requiresGuardian: true })
      await accept(wrapper)
      await wrapper.find('[data-testid="image-contract-send"]').trigger('click')

      await wrapper.find('[data-testid="guardian-back"]').trigger('click')

      expect(wrapper.find('[data-testid="image-contract-title"]').text()).toBe("Votre contrat d'image")
    })
  })

  describe('nom refusé par le serveur', () => {
    it('affiche « Modifier mon nom » à l\'étape code, qui revient à l\'étape 1 en gardant le nom', async () => {
      requestCodeMock.mockResolvedValue(codeReply())
      const submit = vi.fn().mockResolvedValue({ ok: false, code: 'SIGNATURE_NAME_INVALID', message: 'Nom refusé.' })
      const wrapper = mountDialog({}, submit)
      await wrapper.find('[data-testid="image-contract-name"]').setValue('Jean Dupont')
      await goToCodeStep(wrapper)
      await typeCode(wrapper, '123456')
      await wrapper.find('[data-testid="image-contract-confirm"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('[data-testid="image-contract-error"]').text()).toBe('Nom refusé.')
      await wrapper.find('[data-testid="image-contract-edit-name"]').trigger('click')

      expect(wrapper.find('[data-testid="image-contract-title"]').text()).toBe("Votre contrat d'image")
      expect((wrapper.find('[data-testid="image-contract-name"]').element as HTMLInputElement).value).toBe('Jean Dupont')
      expect(wrapper.find('[data-testid="image-contract-error"]').exists()).toBe(false)
    })

    it('pas de lien pour les autres erreurs', async () => {
      requestCodeMock.mockResolvedValue(codeReply())
      const wrapper = mountDialog({}, vi.fn().mockResolvedValue({ ok: false, code: 'X', message: 'Autre.' }))
      await goToCodeStep(wrapper)
      await typeCode(wrapper, '123456')
      await wrapper.find('[data-testid="image-contract-confirm"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('[data-testid="image-contract-edit-name"]').exists()).toBe(false)
    })
  })

  describe('parcours mission', () => {
    it('Producteur : motif mission_publish et bouton « Signer et publier »', async () => {
      requestCodeMock.mockResolvedValue(codeReply())
      const wrapper = mountDialog({ flow: 'mission', role: 'producer' })

      await goToCodeStep(wrapper)

      expect(requestCodeMock).toHaveBeenCalledWith({ purpose: 'mission_publish' })
      expect(wrapper.find('[data-testid="image-contract-confirm"]').text()).toBe('Signer et publier')
      expect(wrapper.find('[data-testid="image-contract-no-code-hint"]').exists()).toBe(false)
    })

    it('Face : motif candidature_confirm avec la candidature, bouton « Signer et confirmer »', async () => {
      requestCodeMock.mockResolvedValue(codeReply())
      const wrapper = mountDialog({ flow: 'mission', role: 'face', candidatureId: 'c-1' })

      await goToCodeStep(wrapper)

      expect(requestCodeMock).toHaveBeenCalledWith({ purpose: 'candidature_confirm', candidature_id: 'c-1' })
      expect(wrapper.find('[data-testid="image-contract-confirm"]').text()).toBe('Signer et confirmer')
      expect(wrapper.find('[data-testid="image-contract-no-code-hint"]').text()).toBe(
        'Sans ce code, votre participation ne peut pas être confirmée.',
      )
    })

    it('Face mineure : le code part au parent, motif candidature_confirm et encadré adapté', async () => {
      requestCodeMock.mockResolvedValue(codeReply('m•••@gmail.com'))
      const wrapper = mountDialog({ flow: 'mission', role: 'face', candidatureId: 'c-1', requiresGuardian: true })
      await accept(wrapper)
      await wrapper.find('[data-testid="image-contract-send"]').trigger('click')

      expect(wrapper.find('[data-testid="image-contract-code-notice-text"]').text()).toBe(
        'Il reçoit le contrat et le code par e-mail. Sans ce code, votre participation ne peut pas être confirmée.',
      )

      await wrapper.find('[data-testid="guardian-name"]').setValue('Marie Dossou')
      await wrapper.find('[data-testid="guardian-relation-mere"]').trigger('click')
      await wrapper.find('[data-testid="guardian-email"]').setValue('marie@gmail.com')
      await wrapper.find('[data-testid="guardian-send"]').trigger('click')
      await flushPromises()

      expect(requestCodeMock).toHaveBeenCalledWith({
        purpose: 'candidature_confirm',
        candidature_id: 'c-1',
        guardian_name: 'Marie Dossou',
        guardian_relation: 'mere',
        guardian_email: 'marie@gmail.com',
      })
    })

    it('Face, mission produit seul : dernière ligne protégée sur la réception du produit', () => {
      const wrapper = mountDialog({ flow: 'mission', role: 'face', candidatureId: 'c-1', productOnly: true })

      const items = wrapper.findAll('[data-testid="image-contract-protected"] li').map((li) => li.text())
      expect(items.at(-1)).toBe('Pas d\'exploitation de votre image avant la réception du produit.')
      expect(items).toHaveLength(4)
    })

    it.each([
      ['mission classique ou hybride', { flow: 'mission', role: 'face', candidatureId: 'c-1' }],
      ['booking', { role: 'face', bookingId: 'b-1' }],
      ['booking avec productOnly', { role: 'face', bookingId: 'b-1', productOnly: true }],
    ])('dernière ligne inchangée : %s', (_name, props) => {
      const wrapper = mountDialog(props as never)

      const items = wrapper.findAll('[data-testid="image-contract-protected"] li').map((li) => li.text())
      expect(items.at(-1)).toBe('Pas d\'exploitation de votre image avant votre paiement.')
    })

    it('le parcours booking reste inchangé par défaut', async () => {
      requestCodeMock.mockResolvedValue(codeReply())
      const producer = mountDialog()
      await goToCodeStep(producer)
      expect(requestCodeMock).toHaveBeenLastCalledWith({ purpose: 'booking_request' })
      expect(producer.find('[data-testid="image-contract-confirm"]').text()).toBe('Signer et envoyer la demande')

      const face = mountDialog({ role: 'face', bookingId: 'b-9' })
      await goToCodeStep(face)
      expect(requestCodeMock).toHaveBeenLastCalledWith({ purpose: 'booking_accept', booking_id: 'b-9' })
      expect(face.find('[data-testid="image-contract-confirm"]').text()).toBe('Signer et accepter le booking')
      expect(face.find('[data-testid="image-contract-no-code-hint"]').text()).toBe(
        'Sans ce code, le booking ne peut pas être accepté.',
      )
    })
  })

  it('émet close avec le bouton Fermer et avec Échap', async () => {
    const wrapper = mountDialog()

    await wrapper.find('[data-testid="image-contract-close"]').trigger('click')
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))

    expect(wrapper.emitted('close')).toHaveLength(2)
    wrapper.unmount()
  })

  it('ne rend rien tant qu\'il est fermé', () => {
    const wrapper = mountDialog({ isOpen: false })
    expect(wrapper.find('[data-testid="image-contract-dialog"]').exists()).toBe(false)
  })
})
