import { z } from 'zod'
import { toTypedSchema } from '@vee-validate/zod'
import type { TypedSchema } from 'vee-validate'

export interface PasswordChangeValues {
  current_password?: string
  new_password: string
  new_password_confirmation: string
}

/**
 * Zod schema for password change validation
 * Matches backend validation rules in ChangePasswordRequest
 *
 * `hasPassword = false` is the OAuth-only case: there is nothing to confirm, so
 * the same form doubles as "set a password" (the backend endpoint does too).
 */
const passwordChangeSchema = (hasPassword: boolean): z.ZodType<PasswordChangeValues> => {
  const base = z.object({
    current_password: hasPassword
      ? z
          .string({ message: 'Le mot de passe actuel est obligatoire' })
          .min(1, 'Le mot de passe actuel est obligatoire')
      : z.string().optional().or(z.literal('')),
    new_password: z
      .string({ message: 'Le nouveau mot de passe est obligatoire' })
      .min(8, 'Le mot de passe doit contenir au moins 8 caractères')
      .regex(/[A-Z]/, 'Le mot de passe doit contenir au moins une majuscule')
      .regex(/\d/, 'Le mot de passe doit contenir au moins un chiffre'),
    new_password_confirmation: z
      .string({ message: 'La confirmation est obligatoire' })
      .min(1, 'La confirmation est obligatoire'),
  })

  return base
    .refine((data) => !hasPassword || data.new_password !== data.current_password, {
      message: 'Le nouveau mot de passe doit être différent de l\'ancien',
      path: ['new_password'],
    })
    .refine((data) => data.new_password === data.new_password_confirmation, {
      message: 'Les mots de passe ne correspondent pas',
      path: ['new_password_confirmation'],
    })
}

/**
 * Typed schema for VeeValidate.
 */
export const passwordChangeValidationSchema = (
  hasPassword = true
): TypedSchema<PasswordChangeValues> =>
  toTypedSchema(passwordChangeSchema(hasPassword))
