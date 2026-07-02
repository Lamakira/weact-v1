import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useUgcDeliverable } from '../useUgcDeliverable'
import { faceApi } from '@/features/face/services/faceApi'
import type { Deliverable, DeliverableResponse, UgcUploadProgress } from '@/components/ugc'

vi.mock('@/features/face/services/faceApi', () => ({
  faceApi: { uploadDeliverable: vi.fn() },
}))

function makeDeliverable(overrides: Partial<Deliverable> = {}): Deliverable {
  return {
    id: 'deliverable-uuid-1',
    kind: 'unboxing',
    kind_label: 'Unboxing',
    validation_status: 'in_review',
    validation_status_label: 'En cours de validation',
    review_note: null,
    validated_at: null,
    chrono_started_at: '2026-06-12T12:00:00+00:00',
    deadline_at: '2026-06-19T12:00:00+00:00',
    duree_seconds: 42,
    created_at: '2026-06-12T13:00:00+00:00',
    ...overrides,
  }
}

const okFile = () => new File(['x'], 'unboxing.mp4', { type: 'video/mp4' })

/** Erreur axios-like portant l'envelope FIX-22.2 { error: { code, message } }. */
function makeEnvelopeError(code: string, message: string): unknown {
  return {
    isAxiosError: true,
    response: { status: 422, data: { error: { code, message } } },
  }
}

/** Erreur axios-like de validation Laravel { errors: { video: [...] } }. */
function makeValidationError(message: string): unknown {
  return {
    isAxiosError: true,
    response: { status: 422, data: { errors: { video: [message] } } },
  }
}

describe('useUgcDeliverable', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('uploads a deliverable through faceApi', async () => {
    vi.mocked(faceApi.uploadDeliverable).mockResolvedValue({ data: makeDeliverable() })

    const { uploadDeliverable, isUploading, error, errorCode } = useUgcDeliverable()
    const file = okFile()
    const deliverable = await uploadDeliverable('s1', file)

    expect(faceApi.uploadDeliverable).toHaveBeenCalledWith('s1', file, expect.any(Function))
    expect(deliverable).toMatchObject({ id: 'deliverable-uuid-1', kind: 'unboxing' })
    expect(isUploading.value).toBe(false)
    expect(error.value).toBeNull()
    expect(errorCode.value).toBeNull()
  })

  it('tracks isUploading across the upload', async () => {
    let resolveRequest!: (value: DeliverableResponse) => void
    vi.mocked(faceApi.uploadDeliverable).mockReturnValue(
      new Promise((resolve) => {
        resolveRequest = resolve
      }),
    )

    const { uploadDeliverable, isUploading } = useUgcDeliverable()
    expect(isUploading.value).toBe(false)

    const pending = uploadDeliverable('s1', okFile())
    expect(isUploading.value).toBe(true)

    resolveRequest({ data: makeDeliverable() })
    await pending
    expect(isUploading.value).toBe(false)
  })

  it('reports upload progress through the callback', async () => {
    const progress: UgcUploadProgress = { loaded: 50, total: 100, percentage: 50 }
    vi.mocked(faceApi.uploadDeliverable).mockImplementation((_id, _file, onProgress) => {
      onProgress?.(progress)
      return Promise.resolve({ data: makeDeliverable() })
    })

    const { uploadDeliverable, uploadProgress } = useUgcDeliverable()
    const pending = uploadDeliverable('s1', okFile())
    // Le callback a couru synchrone avant l'await → la progression est captée.
    expect(uploadProgress.value).toEqual(progress)

    await pending
    // finally remet la progression à null une fois l'upload terminé.
    expect(uploadProgress.value).toBeNull()
  })

  it('rejects an unsupported file type before any request', async () => {
    const { uploadDeliverable, error, errorCode } = useUgcDeliverable()
    const badFile = new File(['x'], 'doc.pdf', { type: 'application/pdf' })

    const result = await uploadDeliverable('s1', badFile)

    expect(result).toBeNull()
    expect(faceApi.uploadDeliverable).not.toHaveBeenCalled()
    expect(error.value).toContain('Format non supporté')
    expect(errorCode.value).toBeNull()
  })

  it('rejects an oversized file before any request', async () => {
    const { uploadDeliverable, error } = useUgcDeliverable()
    const bigFile = okFile()
    Object.defineProperty(bigFile, 'size', { value: 201 * 1024 * 1024, configurable: true })

    const result = await uploadDeliverable('s1', bigFile)

    expect(result).toBeNull()
    expect(faceApi.uploadDeliverable).not.toHaveBeenCalled()
    expect(error.value).toContain('200 Mo')
  })

  it('accepts a .mov file reported with an empty browser MIME type', async () => {
    // Header navigateur souvent vide pour .mov/.avi : la garde MIME souple doit
    // laisser passer (l'extension est primaire ; le backend re-valide le contenu).
    vi.mocked(faceApi.uploadDeliverable).mockResolvedValue({ data: makeDeliverable({ id: 'd-mov' }) })

    const { uploadDeliverable, error } = useUgcDeliverable()
    const movFile = new File(['x'], 'unboxing.mov', { type: '' })

    const deliverable = await uploadDeliverable('s1', movFile)

    expect(faceApi.uploadDeliverable).toHaveBeenCalledWith('s1', movFile, expect.any(Function))
    expect(deliverable).toMatchObject({ id: 'd-mov' })
    expect(error.value).toBeNull()
  })

  it('accepts an .avi file reported as video/avi', async () => {
    // Certains navigateurs rapportent `video/avi` (absent de config/ugc.php mais
    // ré-inclus côté client) : ne doit pas être rejeté avant le POST.
    vi.mocked(faceApi.uploadDeliverable).mockResolvedValue({ data: makeDeliverable({ id: 'd-avi' }) })

    const { uploadDeliverable, error } = useUgcDeliverable()
    const aviFile = new File(['x'], 'unboxing.avi', { type: 'video/avi' })

    const deliverable = await uploadDeliverable('s1', aviFile)

    expect(faceApi.uploadDeliverable).toHaveBeenCalledWith('s1', aviFile, expect.any(Function))
    expect(deliverable).toMatchObject({ id: 'd-avi' })
    expect(error.value).toBeNull()
  })

  it('captures the ALREADY_UPLOADED envelope code on failure', async () => {
    vi.mocked(faceApi.uploadDeliverable).mockRejectedValue(
      makeEnvelopeError('ALREADY_UPLOADED', 'Votre vidéo Unboxing a déjà été déposée pour ce deal.'),
    )

    const { uploadDeliverable, error, errorCode } = useUgcDeliverable()
    const result = await uploadDeliverable('s1', okFile())

    expect(result).toBeNull()
    expect(errorCode.value).toBe('ALREADY_UPLOADED')
    expect(error.value).toBe('Votre vidéo Unboxing a déjà été déposée pour ce deal.')
  })

  it('surfaces the server validation message when the video is rejected', async () => {
    vi.mocked(faceApi.uploadDeliverable).mockRejectedValue(
      makeValidationError('Format non supporté.'),
    )

    const { uploadDeliverable, error, errorCode } = useUgcDeliverable()
    const result = await uploadDeliverable('s1', okFile())

    expect(result).toBeNull()
    expect(error.value).toBe('Format non supporté.')
    // Pas d'envelope code sur une validation errors.video → null (branche « autre »).
    expect(errorCode.value).toBeNull()
  })
})
