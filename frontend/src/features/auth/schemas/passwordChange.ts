import { z } from 'zod'
import { toTypedSchema } from '@vee-validate/zod'

/**
 * Zod schema for password change validation
 * Matches backend validation rules in ChangePasswordRequest
 */
export const passwordChangeSchema = z
  .object({
    current_password: z
      .string({ message: 'Le mot de passe actuel est obligatoire' })
      .min(1, 'Le mot de passe actuel est obligatoire'),
    new_password: z
      .string({ message: 'Le nouveau mot de passe est obligatoire' })
      .min(8, 'Le mot de passe doit contenir au moins 8 caractères')
      .regex(/[A-Z]/, 'Le mot de passe doit contenir au moins une majuscule')
      .regex(/\d/, 'Le mot de passe doit contenir au moins un chiffre'),
    new_password_confirmation: z
      .string({ message: 'La confirmation est obligatoire' })
      .min(1, 'La confirmation est obligatoire'),
  })
  .refine((data) => data.new_password !== data.current_password, {
    message: 'Le nouveau mot de passe doit être différent de l\'ancien',
    path: ['new_password'],
  })
  .refine((data) => data.new_password === data.new_password_confirmation, {
    message: 'Les mots de passe ne correspondent pas',
    path: ['new_password_confirmation'],
  })

/**
 * Typed schema for VeeValidate
 */
export const passwordChangeValidationSchema = toTypedSchema(passwordChangeSchema)

/**
 * Type inferred from the schema
 */
export type PasswordChangeFormData = z.infer<typeof passwordChangeSchema>
