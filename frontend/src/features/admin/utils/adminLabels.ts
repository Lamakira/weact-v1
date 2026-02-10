const roleLabels: Record<string, string> = {
  superadmin: 'Super Admin',
  admin: 'Admin',
  editor: 'Éditeur',
}

const roleColors: Record<string, string> = {
  superadmin: 'bg-purple-100 text-purple-700',
  admin: 'bg-blue-100 text-blue-700',
  editor: 'bg-teal-100 text-teal-700',
}

export function getAdminRoleLabel(role: string | null): string {
  return role ? roleLabels[role] ?? role : '—'
}

export function getAdminRoleColor(role: string | null): string {
  return role ? roleColors[role] ?? 'bg-gray-100 text-gray-700' : 'bg-gray-100 text-gray-400'
}
