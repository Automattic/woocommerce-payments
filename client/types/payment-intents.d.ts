/**
 * Internal dependencies
 */
import { PaymentMethodDetails } from './charges';

export interface PaymentIntentCharge {
	id: string;
	amount: number;
	created: number;
	payment_method_details: PaymentMethodDetails;
}

export interface PaymentIntent {
	id: string;
	amount: number;
	charge: PaymentIntentCharge;
	created: number;
	currency: string;
	customer: string;
	metadata: Record< string, any >;
	payment_method: string;
	status: string;
}
