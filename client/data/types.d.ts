/** @format */

/**
 * Internal Dependencies
 */
import { CapitalState } from './capital/types';
import { PaymentIntentsState } from './payment-intents/types';

export interface State {
	capital?: CapitalState;
	paymentIntents?: PaymentIntentsState;
}
