/**
 * Amount in francs CFA as shown in the data tables: « 100 000 XOF ».
 * (XOF is the code used by most user-facing amounts in the app.)
 */
export function formatXof(amount: number): string {
  return (
    new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'XOF',
      currencyDisplay: 'code',
      maximumFractionDigits: 0,
    })
      .format(amount)
      .replace('XOF', '')
      .trim() + ' XOF'
  )
}
