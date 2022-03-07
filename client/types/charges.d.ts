/**
 * Internal dependencies
 */
import { BalanceTransaction } from './balance-transactions';
import { Dispute } from './disputes';

interface ChargeRefund {
	balance_transaction: BalanceTransaction;
}

interface ChargeRefunds {
	data: ChargeRefund[];
}

export interface Charge {
	id: string;
	amount: number;
	amount_captured: number;
	amount_refunded: number;
	application_fee_amount: number;
	balance_transaction: BalanceTransaction;
	billing_details: {
		name: string;
		email: string;
		address: {
			country: string;
		};
	};
	captured: boolean;
	created: number;
	currency: string;
	dispute?: Dispute;
	disputed: boolean;
	order: null | {
		number: number;
		url: string;
		customer_url: string;
		subscriptions?: Array< { number: number; url: string } >;
	};
	outcome: null | {
		network_status: string;
		reason: string;
		risk_level:
			| 'normal'
			| 'elevated'
			| 'highest'
			| 'not_assessed'
			| 'unknown';
		risk_score: number;
		rule: string;
		seller_message: string;
		type: string;
	};
	paid: boolean;
	paydown: {
		amount: number;
	};
	payment_intent: null | string;
	payment_method_details: {
		card?: any;
		type: 'card';
	};
	refunded: boolean;
	refunds: ChargeRefunds;
	status: string;
}

export interface ChargeAmounts {
	amount: number;
	currency: string;
	fee: number;
	net: number;
	refunded: number;
}
