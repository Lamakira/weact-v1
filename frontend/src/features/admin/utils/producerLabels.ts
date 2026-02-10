const typeLabels: Record<string, string> = {
  agency: 'Agence',
  particulier: 'Particulier',
}

const typeColors: Record<string, string> = {
  agency: 'bg-indigo-100 text-indigo-700',
  particulier: 'bg-teal-100 text-teal-700',
}

export function getProducerTypeLabel(type: string | null): string {
  return type ? typeLabels[type] ?? type : '—'
}

export function getProducerTypeColor(type: string | null): string {
  return type ? typeColors[type] ?? 'bg-gray-100 text-gray-700' : 'bg-gray-100 text-gray-400'
}
