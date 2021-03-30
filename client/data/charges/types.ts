/* eslint-disable camelcase */

/**
 * External dependencies
 */
import type { PaymentMethod } from '@stripe/stripe-js';
/**
 * Internal dependencies
 */
import type { Charge } from 'data/stripe';

// We don't extend WCPayPaymentMethod here even though that's /technically/ what we're doing,
// because we have to make `card` optional if we do, even though we know that's not possible here.
export interface CardPaymentMethod extends PaymentMethod {
	type: 'card';
	card: PaymentMethod.Card;
}

export interface WCPayCharge extends Charge {
	payment_method_details: PaymentMethod | CardPaymentMethod | null;
}
