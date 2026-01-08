import { z } from 'zod'
import { toTypedSchema } from '@vee-validate/zod'

/**
 * Zod schema for Face registration validation
 * Matches backend validation rules
 */
export const faceRegistrationSchema = z
  .object({
    nom: z
      .string({ message: 'Le nom est obligatoire' })
      .min(1, 'Le nom est obligatoire')
      .max(255, 'Le nom ne peut pas dépasser 255 caractères'),

    prenom: z
      .string({ message: 'Le prénom est obligatoire' })
      .min(1, 'Le prénom est obligatoire')
      .max(255, 'Le prénom ne peut pas dépasser 255 caractères'),

    username: z
      .string({ message: "Le nom d'utilisateur est obligatoire" })
      .min(1, "Le nom d'utilisateur est obligatoire")
      .max(50, "Le nom d'utilisateur ne peut pas dépasser 50 caractères")
      .regex(
        /^[a-zA-Z0-9_]+$/,
        "Le nom d'utilisateur ne peut contenir que des lettres, chiffres et underscores"
      ),

    email: z
      .string({ message: "L'email est obligatoire" })
      .min(1, "L'email est obligatoire")
      .email("L'email doit être une adresse email valide"),

    password: z
      .string({ message: 'Le mot de passe est obligatoire' })
      .min(8, 'Le mot de passe doit contenir au moins 8 caractères')
      .regex(/[A-Z]/, 'Le mot de passe doit contenir au moins une majuscule')
      .regex(/\d/, 'Le mot de passe doit contenir au moins un chiffre'),

    password_confirmation: z
      .string({ message: 'La confirmation du mot de passe est obligatoire' })
      .min(1, 'La confirmation du mot de passe est obligatoire'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'La confirmation du mot de passe ne correspond pas',
    path: ['password_confirmation'],
  })

/**
 * Typed schema for VeeValidate
 */
export const faceRegistrationValidationSchema = toTypedSchema(faceRegistrationSchema)

/**
 * Type inferred from the schema
 */
export type FaceRegistrationFormData = z.infer<typeof faceRegistrationSchema>
