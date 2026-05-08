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

export interface ChargeDispute extends Omit< Dispute, 'charge' > {
	charge: string;
}

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
	dispute?: null | ChargeDispute;
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
	/**
	 * Server-driven fee_breakdown_v1 envelope (experimental). When present,
	 * header totals should read from `totals.fee` / `totals.net` rather
	 * than deriving from `balance_transaction.fee` + `application_fee_amount`.
	 */
	fee_breakdown_v1?: {
		rows: Array< {
			key: string;
			kind: 'fee' | 'adjustment' | 'tax';
			label: string | null;
			/** Magnitude for arithmetic compatibility (always >= 0 for fee/tax). */
			amount: number;
			/** Signed for direct rendering — no -Math.abs() needed. */
			display_amount?: number;
			currency: string;
			rate: null | {
				percentage?: number;
				fixed?: number;
				fixed_currency?: string;
				capped?: boolean;
				cap_amount?: number;
				/** Pre-formatted percentage string (e.g. "2.9%", "22.00%"). */
				percentage_display?: string;
			};
			meta: null | Record< string, unknown >;
		} >;
		totals: {
			fee: {
				amount: number;
				display_amount?: number;
				currency: string;
				rate?: {
					percentage?: number;
					fixed?: number;
					fixed_currency?: string;
					capped?: boolean;
					cap_amount?: number;
					percentage_display?: string;
				};
			};
			tax: {
				amount: number;
				display_amount?: number;
				currency: string;
			};
			net: {
				amount: number;
				currency: string;
			};
			gross: {
				amount: number;
				currency: string;
			};
			/** Convenience: fee + tax in store currency (what Stripe deducted). */
			fee_plus_tax?: {
				amount: number;
				currency: string;
			};
		};
		notes: Array< {
			code: string;
			severity?: 'info' | 'warning' | 'error';
			meta?: Record< string, unknown >;
		} >;
		sources?: Record< string, unknown >;
		/** Cross-currency charges only. Pre-formatted for display. */
		fx?: {
			rate_display: string;
			from_currency: string;
			to_currency: string;
			from_amount: number;
			to_amount: number;
		};
	};
}

export interface ChargeAmounts {
	amount: number;
	currency: string;
	fee: number;
	net: number;
	refunded: number;
}
