import { z } from 'zod'

/**
 * Login form validation schema
 */
export const loginSchema = z.object({
  email: z
    .string({ message: "L'email est obligatoire" })
    .min(1, "L'email est obligatoire")
    .email("L'email doit être une adresse email valide"),
  password: z
    .string({ message: 'Le mot de passe est obligatoire' })
    .min(1, 'Le mot de passe est obligatoire'),
})


