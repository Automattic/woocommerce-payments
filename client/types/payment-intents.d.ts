/**
 * Internal dependencies
 */
import { Charge } from './charges';

export interface PaymentIntent {
	id: string;
	amount: number;
	charge: Charge;
	created: number;
	currency: string;
	customer: string;
	metadata: Record< string, any >;
	payment_method: string;
	status: string;
}
