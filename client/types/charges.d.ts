/**
 * Internal dependencies
 */
import { BalanceTransaction } from './balance-transactions';
import { Dispute } from './disputes';
import PAYMENT_METHOD_IDS from 'wcpay/constants/payment-method';

interface ChargeBillingDetails {
	email: null | string;
	name: null | string;
	phone: null | string;
	address: {
		city: null | string;
		country: null | string;
		line1: null | string;
		line2: null | string;
		postal_code: null | string;
		state: null | string;
	};
	formatted_address?: string;
}

interface Level3LineItem {
	product_description: string;
	product_name: string;
	quantity: number;
	unit_cost: number;
}

interface Level3Data {
	line_items: Level3LineItem[];
}

interface ChargeRefund {
	balance_transaction: BalanceTransaction;
}

interface ChargeRefunds {
	data: ChargeRefund[];
}

export type PaymentMethodDetails = {
	[ T in PAYMENT_METHOD_IDS ]: {
		type: T;
	} & Record< T, Record< string, unknown > >;
}[ PAYMENT_METHOD_IDS ];

export type OutcomeRiskLevel =
	| 'normal'
	| 'elevated'
	| 'highest'
	| 'not_assessed'
	| 'unknown';

export interface Charge {
	id: string;
	amount: number;
	amount_captured: number;
	amount_refunded: number;
	application_fee_amount: number;
	balance_transaction: BalanceTransaction;
	billing_details: ChargeBillingDetails;
	captured?: boolean;
	created: number;
	currency: string;
	dispute?: null | Dispute;
	disputed: boolean;
	order: null | OrderDetails;
	outcome: null | {
		network_status: string;
		reason: null | string;
		risk_level: OutcomeRiskLevel;
		risk_score: number;
		rule?: string;
		seller_message: string;
		type: string;
	};
	paid: boolean;
	paydown: null | {
		amount: number;
	};
	payment_intent: null | string;
	payment_method: string;
	payment_method_details: PaymentMethodDetails;
	refunded: boolean;
	refunds: null | ChargeRefunds;
	status: string;
	reader_model?: string;
	platform?: string;
	level3?: Level3Data;
}

export interface ChargeAmounts {
	amount: number;
	currency: string;
	fee: number;
	net: number;
	refunded: number;
}
