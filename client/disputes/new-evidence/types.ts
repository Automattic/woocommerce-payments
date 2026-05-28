/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { Dispute, DisputeReason, ProductType } from 'wcpay/types/disputes';
import { Charge } from 'wcpay/types/charges';

export interface ExtendedDispute
	extends Omit< Dispute, 'evidence' | 'charge' > {
	merchant_name?: string;
	merchant_address?: string;
	merchant_email?: string;
	merchant_phone?: string;
	evidence: {
		[ key: string ]:
			| string
			| Record< string, boolean >
			| Record< string, string >;
	};
	charge: Charge;
}

export interface AccountDetails {
	name: string;
	support_address_city: string;
	support_address_country: string;
	support_address_line1: string;
	support_address_line2: string;
	support_address_postal_code: string;
	support_address_state: string;
}

export interface CoverLetterData {
	merchantAddress: string;
	merchantName: string;
	merchantEmail: string;
	merchantPhone: string;
	today: string;
	acquiringBank: string;
	caseNumber: string;
	transactionId: string;
	transactionDate: string;
	customerName: string;
	product: string;
	orderDate: string;
	deliveryDate: string;
	refundStatus?: string;
	duplicateStatus?: string;
}

export interface CoverLetterProps {
	value: string;
	onChange: ( value: string ) => void;
	readOnly?: boolean;
}

export interface RecommendedDocument {
	key: string;
	label: string;
	description: string | null;
	order: number;
}

export interface DocumentField {
	key: string;
	label: string;
	description: string | null;
	fileName?: string;
	fileSize?: number;
	onFileChange: ( key: string, file: File ) => Promise< void >;
	onFileRemove: () => Promise< void >;
	uploaded?: boolean;
	readOnly?: boolean;
	isBusy?: boolean;
}

export interface FileUploadControlProps {
	fileName?: string;
	fileSize?: number;
	description: string | null;
	onFileChange: ( file: File ) => void;
	onFileRemove: () => void;
	disabled?: boolean;
	isDone?: boolean;
	isBusy?: boolean;
	accept?: string;
	label: string;
}

export interface RecommendedDocumentsProps {
	fields: DocumentField[];
	readOnly?: boolean;
}

export interface BaseEvidence {
	product_description: string;
	receipt: string;
	customer_communication: string;
	customer_signature: string;
	refund_policy: string;
	duplicate_charge_documentation: string;
	shipping_documentation: string;
	service_documentation: string;
	cancellation_policy: string;
	cancellation_rebuttal: string;
	access_activity_log: string;
	uncategorized_file: string;
	uncategorized_text: string;
	shipping_carrier: string;
	shipping_date: string;
	shipping_tracking_number: string;
	shipping_address: string;
	customer_purchase_ip: string;
	[ key: string ]: string;
}

export type EvidenceState = Partial< BaseEvidence > & {
	[ key: string ]: string | undefined;
};

export type EvidenceFieldState =
	| 'provided'
	| 'expected_missing'
	| 'optional_missing';

export interface EvidenceFieldStatus {
	key: string;
	label: string;
	state: EvidenceFieldState;
}

/**
 * Outcome framing carried over from the dispute status. Used to gate which
 * catalog entries fire. The component's two visual sections (Positives vs
 * Critical+Tip) are driven by urgency, not by outcome.
 */
export type RecommendationOutcome = 'could_help' | 'keep_doing';

/**
 * Visual urgency of a recommendation. Drives the leading icon (shape + color)
 * and which card section the entry renders under (title text color stays the
 * same across all urgencies; only the icon and section reflect urgency).
 *   - `critical` (amber, `caution`):   "fix this next time" — high-lift gap on a lost dispute.
 *   - `tip`      (amber, `caution`):   "would have made the case stronger" — soft suggestion.
 *   - `positive` (green, `published`): "you did this right" — reinforcement.
 *
 * No red: recommendations coach, they don't flag errors (design 2026-05-26).
 */
export type RecommendationUrgency = 'critical' | 'positive' | 'tip';

/**
 * Count-based predicate over a set of evidence field keys. `min` and `max`
 * (inclusive) cap how many of the listed keys must satisfy the underlying
 * condition (provided or missing). Defaults are applied by `matchesCount()`
 * in recommendations.ts: `max` = keys.length; `min` = 1, or 0 when `max` is
 * set explicitly (so a `max`-only predicate such as Cluster 15's `max: 0`
 * stays satisfiable). See that function for the authoritative rule.
 */
export interface FieldCountPredicate {
	keys: string[];
	min?: number;
	max?: number;
}

/**
 * Conditions under which a catalog entry fires. AND across clauses.
 * Absent clauses don't constrain.
 */
export interface RecommendationWhen {
	outcome: RecommendationOutcome;
	reasonIn: DisputeReason[];
	productTypeIn?: ProductType[];
	requireProvided?: FieldCountPredicate;
	requireMissing?: FieldCountPredicate;
}

export interface Recommendation {
	id: string;
	title: string;
	body: string;
	urgency: RecommendationUrgency;
	when: RecommendationWhen;
	/**
	 * Q6 win-rate lift in percentage points, when known. Used to rank
	 * recommendations within a section when capping (top N by lift).
	 * Entries without a measured lift sort to the bottom.
	 */
	lift?: number;
	/**
	 * When this entry fires, hide all other `critical` entries on the same
	 * dispute. Used by the catch-all no-evidence recommendation so a
	 * disengaged merchant sees one clear message rather than a stack of
	 * "missing X" entries.
	 */
	suppressOtherCriticals?: boolean;
	/**
	 * Tombstone: retired entries stay in the catalog (so the id is never
	 * reused as a Tracks join key) but are dropped from runtime results.
	 */
	retired?: boolean;
}

export interface RecommendationContext {
	reason: string;
	productType: string;
	outcome: RecommendationOutcome;
	evidence: Record< string, unknown >;
}
