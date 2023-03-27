/** @format */

/**
 * Internal Dependencies
 */
import { CapitalState } from './capital/types';
import { FraudOutcomesState } from './fraud-outcomes/types';
import { OrdersState } from './orders/types';
import { PaymentIntentsState } from './payment-intents/types';

export interface State {
	capital?: CapitalState;
	fraudOutcomes?: FraudOutcomesState;
	paymentIntents?: PaymentIntentsState;
	orders?: OrdersState;
}
