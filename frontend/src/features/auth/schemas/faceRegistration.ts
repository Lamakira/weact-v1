import { z } from 'zod'
import { toTypedSchema } from '@vee-validate/zod'

/**
 * Zod schema for Face registration validation
 * Matches backend validation rules
 *
 * Deliberately minimal: sexe, nationalite, pays and whatsapp_number are collected
 * later in the profile completion flow. `username` is generated server-side and
 * editable from the profile.
 */
const faceRegistrationSchema = z
  .object({
    nom: z
      .string({ message: 'Le nom est obligatoire' })
      .min(1, 'Le nom est obligatoire')
      .max(255, 'Le nom ne peut pas dépasser 255 caractères'),

    prenom: z
      .string({ message: 'Le prénom est obligatoire' })
      .min(1, 'Le prénom est obligatoire')
      .max(255, 'Le prénom ne peut pas dépasser 255 caractères'),

    email: z
      .string({ message: "L'email est obligatoire" })
      .min(1, "L'email est obligatoire")
      .email("L'email doit être une adresse email valide"),

    date_naissance: z
      .string({ message: 'La date de naissance est obligatoire.' })
      .min(1, 'La date de naissance est obligatoire.'),

    password: z
      .string({ message: 'Le mot de passe est obligatoire' })
      .min(8, 'Le mot de passe doit contenir au moins 8 caractères')
      .regex(/[A-Z]/, 'Le mot de passe doit contenir au moins une majuscule')
      .regex(/\d/, 'Le mot de passe doit contenir au moins un chiffre'),

    accept_cgu: z
      .boolean({
        message:
          'Vous devez avoir 16 ans ou plus et accepter les CGU et la Politique de Confidentialité.',
      })
      .refine((val) => val === true, {
        message:
          'Vous devez avoir 16 ans ou plus et accepter les CGU et la Politique de Confidentialité.',
      }),
  })
  .refine(
    (data) => {
      if (!data.date_naissance) return true
      const birthDate = new Date(data.date_naissance)
      const today = new Date()
      return birthDate < today
    },
    {
      message: 'La date de naissance doit être antérieure à aujourd\'hui.',
      path: ['date_naissance'],
    }
  )
  .refine(
    (data) => {
      if (!data.date_naissance) return true
      const birthDate = new Date(data.date_naissance)
      const minDate = new Date()
      minDate.setFullYear(minDate.getFullYear() - 16)
      return birthDate <= minDate
    },
    {
      message: 'Vous devez avoir au moins 16 ans pour vous inscrire.',
      path: ['date_naissance'],
    }
  )

/**
 * Typed schema for VeeValidate
 */
export const faceRegistrationValidationSchema = toTypedSchema(faceRegistrationSchema)
