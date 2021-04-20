/* eslint-disable camelcase */

/**
 * External dependencies
 */
import type {
	PaymentIntent,
	Source,
	PaymentMethod,
	TokenCreateParams,
} from '@stripe/stripe-js';
/**
 * Internal dependencies
 */
import type { BalanceTransaction } from './balance-transactions';
import type { Dispute } from './disputes';
import type { Customer, DeletedCustomer } from './customer';
import type { Refund } from './refunds';
import type { Review } from './reviews';
import type { ApiList } from './apilist';
import type { StripeMetadata } from './metadata';

// Copied and reworked a bit for our purposes from
// https://github.com/stripe/stripe-node/blob/e195e0723e721f65f822e802ae6e9b154fd2a0fe/types/2020-08-27/Applications.d.ts#L8-L24
export interface Application {
	id: string;
	object: 'application';
	name: string | null;
}

interface FraudDetails {
	stripe_report?: string;
	user_report?: string;
}

interface Level3LineItem {
	discount_amount: number | null;
	product_code: string;
	product_description: string;
	quantity: number | null;
	tax_amount: number | null;
	unit_cost: number | null;
}

interface Level3 {
	customer_reference?: string;
	line_items: Array< Level3LineItem >;
	merchant_reference: string;
	shipping_address_zip?: string;
	shipping_amount?: number;
	shipping_from_zip?: string;
}

interface OutcomeRule {
	action: string;
	id: string;
	predicate: string;
}

interface Outcome {
	network_status: string | null;
	reason: string | null;
	risk_level?: string;
	risk_score?: number;
	rule?: string | OutcomeRule;
	seller_message: string | null;
	type: string;
}

interface TransferData {
	amount: number | null;
	destination: string | TokenCreateParams.Account;
}

interface AlternateStatementDescriptors {
	kana?: string;
	kanji?: string;
}

export interface Charge {
	id: string;
	object: 'charge';
	alternate_statement_descriptors?: AlternateStatementDescriptors;
	amount: number;
	amount_captured: number;
	amount_refunded: number;
	application: string | Application | null;
	application_fee: string | PaymentMethod.BillingDetails | null;
	application_fee_amount: number | null;
	authorization_code?: string;
	balance_transaction: string | BalanceTransaction | null;
	billing_details: PaymentMethod.BillingDetails;
	calculated_statement_descriptor: string | null;
	captured: boolean;
	created: number;
	currency: string;
	customer: string | Customer | DeletedCustomer | null;
	description: string | null;
	destination: string | Account | null;
	dispute: string | Dispute | null;
	disputed: boolean;
	failure_code: string | null;
	failure_message: string | null;
	fraud_details: FraudDetails | null;
	invoice: string | unknown | null;
	level3?: Level3;
	livemode: boolean;
	metadata: StripeMetadata;
	on_behalf_of: string | Account | null;
	order: string | unknown | null;
	outcome: Outcome | null;
	paid: boolean;
	payment_intent: string | PaymentIntent | null;
	payment_method: string | null;
	payment_method_details: PaymentMethod | null;
	receipt_email: string | null;
	receipt_number: string | null;
	receipt_url: string | null;
	refunded: boolean;
	refunds: ApiList< Refund >;
	review: string | Review | null;
	shipping: PaymentIntent.Shipping | null;
	source: Source | null;
	source_transfer: string | unknown | null;
	statement_descriptor: string | null;
	statement_descriptor_suffix: string | null;
	status: string;
	transfer?: string | unknown;
	transfer_data: unknown | null;
	transfer_group: string | null;
}
