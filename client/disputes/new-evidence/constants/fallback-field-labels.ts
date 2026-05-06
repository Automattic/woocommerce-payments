/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Human-readable labels used as a fallback when `findMatrixLabel` cannot
 * resolve a label for a `dispute.evidence` key — either because the key
 * is a text/base field not stored in `evidenceMatrix` (which only carries
 * document-upload cells), or because multiple matched wizard cells
 * disagree on the label (collision) and no single status-specific label
 * is appropriate in the post-resolution view.
 *
 * Consumed by `resolveFieldLabel` (called from `getExpectedFieldStatus`).
 */
// eslint-disable-next-line @typescript-eslint/naming-convention -- This is a constant object.
export const FALLBACK_EVIDENCE_FIELD_LABELS: Record< string, string > = {
	billing_address: __( 'Billing address', 'woocommerce-payments' ),
	cancellation_policy_disclosure: __(
		'Cancellation policy disclosure',
		'woocommerce-payments'
	),
	cancellation_rebuttal: __( 'Cancellation logs', 'woocommerce-payments' ),
	// Base fields are auto-merged into wizard cells at runtime by
	// `getRecommendedDocumentFields`, not stored in `evidenceMatrix`. The
	// outcome-view tri-state helper reads `evidenceMatrix` directly, so it
	// would otherwise render the raw key. Match the labels the wizard uses.
	customer_communication: __(
		'Customer communication',
		'woocommerce-payments'
	),
	customer_signature: __( "Customer's signature", 'woocommerce-payments' ),
	// Document-upload fields used as high-impact picks. The wizard matrix
	// labels each per (reason, productType) context (e.g.
	// `shipping_documentation` is "Return tracking" in CNP cells), but
	// the outcome view uses neutral, Stripe-aligned labels for cells
	// where the wizard matrix has no entry to borrow from (notably the
	// synthesised `multiple` product type).
	duplicate_charge_documentation: __(
		'Duplicate charge documentation',
		'woocommerce-payments'
	),
	duplicate_charge_explanation: __(
		'Duplicate charge explanation',
		'woocommerce-payments'
	),
	product_description: __( 'Product description', 'woocommerce-payments' ),
	receipt: __( 'Order receipt', 'woocommerce-payments' ),
	refund_policy: __( 'Refund policy', 'woocommerce-payments' ),
	refund_refusal_explanation: __(
		'Refund refusal explanation',
		'woocommerce-payments'
	),
	service_date: __( 'Service date', 'woocommerce-payments' ),
	shipping_address: __( 'Shipping address', 'woocommerce-payments' ),
	shipping_carrier: __( 'Shipping carrier', 'woocommerce-payments' ),
	shipping_date: __( 'Shipping date', 'woocommerce-payments' ),
	shipping_documentation: __(
		'Shipping documentation',
		'woocommerce-payments'
	),
	shipping_tracking_number: __(
		'Shipping tracking number',
		'woocommerce-payments'
	),
	// Catch-all field. Surfaces via the wizard matrix scan (not the
	// high-impact or topical maps). Wizard cells label it differently per
	// status branch in some composite-key cells (e.g. "Other documents"
	// vs "Proof of acceptance" in CNP physical), so the neutral fallback
	// kicks in via collision detection in `findMatrixLabel`.
	uncategorized_file: __( 'Other documents', 'woocommerce-payments' ),
};
