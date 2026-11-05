/** Code interne : le serveur a refusé le nom saisi (`signature.name`), le code reçu reste valide. */
export const SIGNATURE_NAME_INVALID = 'SIGNATURE_NAME_INVALID'

/**
 * Erreur de validation portant sur la signature elle-même (clés `signature.*`) : la fenêtre de
 * signature doit rester ouverte avec ce message, pas renvoyer au formulaire. Null sinon.
 * La clé nue `signature` (champ absent, formulaire ouvert avant l'activation) n'est pas concernée.
 */
export function signatureFieldError(
  details: Record<string, string[]> | null | undefined,
): { code: string | null; message: string } | null {
  if (!details) return null
  const keys = Object.keys(details).filter((key) => key.startsWith('signature.') && (details[key]?.length ?? 0) > 0)
  if (keys.length === 0) return null
  const key = keys.includes('signature.name') ? 'signature.name' : keys[0]!
  return {
    code: key === 'signature.name' ? SIGNATURE_NAME_INVALID : null,
    message: details[key]![0]!,
  }
}
