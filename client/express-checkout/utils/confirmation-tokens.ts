/**
 * Internal dependencies
 */
import { getExpressCheckoutData } from './express-checkout-data';

/**
 * Determines whether to use Stripe confirmation tokens or legacy payment methods.
 *
 * This is a global setting controlled by the backend feature flag
 * `isEceUsingConfirmationTokens`. It applies uniformly to ALL express payment
 * methods (Apple Pay, Google Pay, Amazon Pay). No per-method overrides needed —
 * the PHP backend already enforces that Amazon Pay can only be enabled when
 * confirmation tokens are enabled (`is_amazon_pay_enabled()` requires
 * `is_ece_confirmation_tokens_enabled()`).
 *
 * Defaults to true (confirmation tokens enabled) when the flag is absent.
 */
export function shouldUseConfirmationTokens(): boolean {
	return (
		getExpressCheckoutData( 'flags' )?.isEceUsingConfirmationTokens ?? true
	);
}
