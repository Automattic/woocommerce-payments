/**
 * Internal dependencies
 */
import { BalanceTransaction } from './balance-transactions';
import { Dispute } from './disputes';

interface BillingDetails {
	name: string;
}

interface ChargeOutcome {
	network_status: string;
	reason: string;
	risk_level: string;
	risk_score: number;
	rule: string;
	seller_message: string;
	type: string;
}

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
	billing_details: BillingDetails;
	captured: boolean;
	currency: string;
	dispute?: Dispute;
	disputed: boolean;
	outcome: ChargeOutcome;
	paid: boolean;
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
