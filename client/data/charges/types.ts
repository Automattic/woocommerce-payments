/* eslint-disable camelcase */

/**
 * External dependencies
 */
import type { PaymentMethod } from '@stripe/stripe-js';
/**
 * Internal dependencies
 */
import type { Charge } from 'data/stripe';

export interface CardPaymentMethod extends PaymentMethod {
	type: 'card';
	card: PaymentMethod.Card;
}

export interface WCPayCharge extends Charge {
	// We do this so type guards can check for `'card' === payment_method_details.type`.
	payment_method_details: PaymentMethod | CardPaymentMethod | null;
}
