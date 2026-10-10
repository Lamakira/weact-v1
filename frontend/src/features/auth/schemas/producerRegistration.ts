import { z } from 'zod'
import { toTypedSchema } from '@vee-validate/zod'
import type { TypedSchema } from 'vee-validate'
import type { ProducerType } from '../types'

export const PRODUCER_NAME_MAX_LENGTH = 100

export interface ProducerRegistrationValues {
  type: ProducerType
  nom: string
  email: string
  password: string
  accept_cgu: boolean
}

/**
 * Zod schema for Producer registration validation
 * Matches backend validation rules (RegisterProducerRequest).
 *
 * Both account types share a single name input: it is submitted as `agency_name`
 * for an agency and as `nom_complet` for a particulier, which the backend splits
 * into first_name/last_name.
 */
const producerRegistrationSchema = (type: ProducerType): z.ZodType<ProducerRegistrationValues> =>
  z.object({
    type: z.literal(type),

    nom: z
      .string({
        message:
          type === 'agency' ? "Le nom de l'agence est obligatoire" : 'Votre nom complet est obligatoire',
      })
      .min(
        1,
        type === 'agency' ? "Le nom de l'agence est obligatoire" : 'Votre nom complet est obligatoire'
      )
      .max(
        PRODUCER_NAME_MAX_LENGTH,
        `Ce champ ne peut pas dépasser ${PRODUCER_NAME_MAX_LENGTH} caractères`
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

    accept_cgu: z
      .boolean({ message: 'Vous devez accepter les CGU et la Politique de Confidentialité.' })
      .refine((val) => val === true, {
        message: 'Vous devez accepter les CGU et la Politique de Confidentialité.',
      }),
  })

/**
 * Typed schema for VeeValidate, rebuilt when the account type changes.
 */
export const producerRegistrationValidationSchema = (
  type: ProducerType
): TypedSchema<ProducerRegistrationValues> =>
  toTypedSchema(producerRegistrationSchema(type))
