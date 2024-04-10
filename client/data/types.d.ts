/** @format */

/**
 * Internal Dependencies
 */
import { CapitalState } from './capital/types';
import { PaymentIntentsState } from './payment-intents/types';
import { FilesState } from './files/types';

export interface State {
	capital?: CapitalState;
	paymentIntents?: PaymentIntentsState;
	files?: FilesState;
	paymentActivity?: any;
}
