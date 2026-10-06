/**
 * Same-tab redirect to a FedaPay hosted checkout.
 *
 * A pop-up (`window.open`) fired after an awaited API call loses the user
 * activation and is blocked on iOS (Safari, WhatsApp/Facebook in-app browsers).
 * After payment FedaPay redirects the browser back to the backend callback,
 * which routes to the initiating page with `?payment_return=<kind>`.
 *
 * Isolated in its own module so tests can mock it.
 */
export function redirectToCheckout(url: string): void {
  window.location.assign(url)
}
