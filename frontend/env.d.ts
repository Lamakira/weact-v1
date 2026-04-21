/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_REGISTRATION_ENABLED?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
