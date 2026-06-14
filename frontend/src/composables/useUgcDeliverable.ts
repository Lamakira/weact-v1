import { ref, type Ref } from 'vue'
import { isAxiosError } from 'axios'
import { faceApi } from '@/features/face/services/faceApi'
import { getApiErrorMessage } from '@/features/auth/services/authApi'
import type { Deliverable, UgcUploadProgress } from '@/components/ugc'

// Contraintes miroir de backend/config/ugc.php `media` (200 Mo, mp4/mov/avi ;
// PAS de cap de durée — max_duration_seconds = null côté serveur, D-4.2.f).
const ALLOWED_EXTENSIONS = ['.mp4', '.mov', '.avi']
// Liste MIME volontairement plus large que config/ugc.php : le header navigateur
// (`file.type`) est non fiable (souvent '' ou `video/avi` pour .mov/.avi). On
// inclut `video/avi` (calque usePresentationVideo) ; le backend reste autoritatif
// en sniffant le contenu (UploadDeliverableRequest `mimetypes:`, code review 4.2).
const ALLOWED_MIMETYPES = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/avi']
const MAX_FILE_SIZE = 200 * 1024 * 1024 // 200 Mo

interface UseUgcDeliverableReturn {
  isUploading: Ref<boolean>
  uploadProgress: Ref<UgcUploadProgress | null>
  error: Ref<string | null>
  errorCode: Ref<string | null>
  uploadDeliverable: (shipmentId: string, file: File) => Promise<Deliverable | null>
  validateFile: (file: File) => { valid: boolean; error?: string }
  clearError: () => void
}

/**
 * Upload du livrable Unboxing (4.2), dual-owner via l'endpoint shipment
 * owner-agnostic (4.1). Sibling de useUgcShipment : même routing par code
 * d'envelope (ALREADY_UPLOADED / INVALID_STATUS). Validation client type/taille
 * calque usePresentationVideo, SANS contrôle de durée (D-4.2.f).
 */
export function useUgcDeliverable(): UseUgcDeliverableReturn {
  const isUploading = ref(false)
  const uploadProgress = ref<UgcUploadProgress | null>(null)
  const error = ref<string | null>(null)
  const errorCode = ref<string | null>(null)

  function clearError(): void {
    error.value = null
    errorCode.value = null
  }

  function extractErrorCode(err: unknown): string | null {
    return isAxiosError(err)
      ? (((err.response?.data as { error?: { code?: string } } | undefined)?.error?.code) ?? null)
      : null
  }

  function validateFile(file: File): { valid: boolean; error?: string } {
    const name = file.name.toLowerCase()
    const hasExt = ALLOWED_EXTENSIONS.some((ext) => name.endsWith(ext))
    // Garde MIME SOUPLE : l'extension est la garde primaire ; un header navigateur
    // vide/inconnu ('') est toléré (cas fréquent .mov/.avi). On ne rejette que si un
    // header présent ne matche aucun type autorisé. Le backend re-valide le contenu.
    const mimeOk = file.type === '' || ALLOWED_MIMETYPES.includes(file.type)
    if (!hasExt || !mimeOk) {
      return { valid: false, error: 'Format non supporté (MP4, MOV, AVI uniquement)' }
    }
    if (file.size > MAX_FILE_SIZE) {
      return { valid: false, error: 'Vidéo trop volumineuse (max 200 Mo)' }
    }
    return { valid: true }
  }

  async function uploadDeliverable(shipmentId: string, file: File): Promise<Deliverable | null> {
    const check = validateFile(file)
    if (!check.valid) {
      error.value = check.error ?? 'Fichier invalide'
      errorCode.value = null
      return null
    }

    isUploading.value = true
    clearError()
    uploadProgress.value = { loaded: 0, total: file.size, percentage: 0 }

    try {
      const response = await faceApi.uploadDeliverable(shipmentId, file, (p) => {
        uploadProgress.value = p
      })
      return response.data
    } catch (err) {
      error.value = getApiErrorMessage(err)
      errorCode.value = extractErrorCode(err)
      return null
    } finally {
      isUploading.value = false
      uploadProgress.value = null
    }
  }

  return { isUploading, uploadProgress, error, errorCode, uploadDeliverable, validateFile, clearError }
}
