export interface TimelineFeeRate {
	type: string;
	additional_type?: string;
	fee_id: string;
	percentage_rate: number;
	fixed_rate: number;
	currency: string;
}

export interface TimelineFeeExchangeRate {
	from_currency: string;
	to_currency: string;
	from_amount: number;
	to_amount: number;
	rate: number;
}

export interface TimelineFeeTax {
	/** The tax amount in the minimum unit of the currency (e.g. cents) */
	amount: number;
	/** The currency code for the tax amount (e.g. "USD") */
	currency: string;
	/** The tax description code (e.g. "US VAT", "JP JCT") that will be localized */
	description?: string;
	/** The tax percentage rate as a decimal (e.g. 0.21 for 21%). Must be between 0 and 1 */
	percentage_rate?: number;
}

export interface TimelineFeeRates {
	percentage: number;
	fixed: number;
	fixed_currency: string;
	history?: Array< TimelineFeeRate >;
	fee_exchange_rate?: TimelineFeeExchangeRate;
	tax?: TimelineFeeTax;
	before_tax?: TimelineFeeTax;
}

export interface TimelineTransactionDetails {
	customer_currency: string;
	customer_amount: number;
	customer_amount_captured: number;
	customer_fee: number;
	store_currency: string;
	store_amount: number;
	store_amount_captured: number;
	store_fee: number;
}

export interface TimelineDeposit {
	id: string;
	arrival_date: number;
}

export type TimelineFeeBreakdownKind = 'fee' | 'adjustment' | 'tax';

export interface TimelineFeeBreakdownRate {
	percentage?: number;
	fixed?: number;
	fixed_currency?: string;
	/**
	 * Pre-formatted percentage string (e.g. "2.9%"). When provided, the
	 * client renders it verbatim instead of computing `percentage * 100`
	 * and picking its own decimal precision.
	 */
	percentage_display?: string;
}

export interface TimelineFeeBreakdownRow {
	/**
	 * Stable typed key — fallback only. Clients read `display_label` first.
	 */
	key: string;
	kind: TimelineFeeBreakdownKind;
	/** Raw server override — fallback, below `display_label`. */
	label: string | null;
	/**
	 * Pre-resolved merchant-facing label written by the PHP presenter on
	 * envelope arrival. Rendered verbatim; the JS dictionary lookup only
	 * runs when this is absent (e.g. test fixtures built in JS).
	 */
	display_label?: string;
	/**
	 * Pre-formatted rate string (e.g. "2.9% + $0.30", "capped at $5", or
	 * "" when the rate has nothing renderable). Written by the PHP
	 * presenter; consumers fall back to their own formatter only for
	 * unenriched envelopes.
	 */
	display_rate?: string;
	/** Signed amount in the store currency's minor units (e.g. cents). */
	amount: number;
	currency: string;
	rate: TimelineFeeBreakdownRate | null;
	meta: Record< string, unknown > | null;
	/**
	 * Server-signed display amount in minor units. When present, the client
	 * renders it verbatim and skips its own sign coercion. Falls back to
	 * `amount` when omitted.
	 */
	display_amount?: number;
}

export interface TimelineFeeBreakdownTotal {
	amount: number;
	currency: string;
	rate?: TimelineFeeBreakdownRate;
	/** Typed override for the totals headline (e.g. `processing_fee`). */
	key?: string;
	/** Pre-resolved headline label from the PHP presenter. */
	display_label?: string;
	/** Pre-formatted rate string from the PHP presenter ("" when none). */
	display_rate?: string;
	/**
	 * Pre-composed line ("Tax IT VAT (22.00%): -$0.22", "Net payout: $X").
	 * `null` signals "don't render this line" (e.g. zero tax); `undefined`
	 * means unenriched, so consumers fall back to their local composer.
	 */
	display_line?: string | null;
}

export interface TimelineFeeBreakdownTotals {
	fee: TimelineFeeBreakdownTotal;
	tax: TimelineFeeBreakdownTotal;
	/**
	 * Current-state net — folds post-capture refunds, dispute balance
	 * adjustments, and paydown. Source of truth for the summary card
	 * "Net" value and the `_wcpay_net` order meta.
	 */
	net: TimelineFeeBreakdownTotal;
	/**
	 * Capture-time net — what the merchant received at the moment of
	 * capture, pre-refund / pre-dispute / pre-paydown. Source of truth
	 * for the timeline captured-event "Net payout: X" line, which is a
	 * historical record (later refunds get their own timeline entries).
	 */
	capture_net: TimelineFeeBreakdownTotal;
	gross: TimelineFeeBreakdownTotal;
}

export interface TimelineFeeBreakdownNote {
	code: string;
	severity?: 'info' | 'warning' | 'error';
	meta?: Record< string, unknown >;
	/**
	 * Pre-resolved merchant-facing text from the PHP presenter. `null`
	 * signals an internal-only code the presenter chose to suppress —
	 * consumers drop the note rather than fall through to a key lookup.
	 */
	display_text?: string | null;
}

export interface TimelineFeeBreakdownFx {
	/**
	 * Pre-formatted exchange-rate string (e.g. "1 USD → 0.851712 EUR").
	 * When present, the client renders it directly — no `customer_amount /
	 * store_amount` division, no re-inversion, no precision choice.
	 */
	rate_display?: string;
	from_currency?: string;
	to_currency?: string;
	from_amount?: number;
	to_amount?: number;
}

export interface TimelineFeeBreakdown {
	rows: TimelineFeeBreakdownRow[];
	totals: TimelineFeeBreakdownTotals;
	notes: TimelineFeeBreakdownNote[];
	sources?: Record< string, unknown >;
	fx?: TimelineFeeBreakdownFx;
}

export interface TimelineItem {
	type: string;
	datetime: number;
	acquirer_reference_number?: string;
	acquirer_reference_number_status?: string;
	amount?: number;
	amount_captured?: number;
	amount_refunded?: number;
	currency?: string;
	deposit?: TimelineDeposit;
	dispute_id?: string;
	evidence_due_by?: number;
	failure_reason?: string;
	failure_transaction_id?: string;
	fee?: number;
	fee_rates?: TimelineFeeRates;
	/**
	 * Server-authoritative display envelope. When present the client renders
	 * from here verbatim; when absent the legacy `fee_rates` / `fee` path is
	 * used. Covers every event that carries fees (captured, disputes,
	 * financing paydowns, etc.), so order notes, the fees-breakdown panel,
	 * and the order page "Transaction Fee" row all read from one source.
	 */
	fee_breakdown_v1?: TimelineFeeBreakdown;
	loan_id?: string;
	reason?: string;
	transaction_details?: TimelineTransactionDetails;
	transaction_id?: string;
	user_id?: number;
}
