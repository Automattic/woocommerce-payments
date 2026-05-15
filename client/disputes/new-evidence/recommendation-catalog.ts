/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { getAdminUrl } from 'wcpay/utils';
import type { Recommendation } from './types';

/**
 * Catalog of merchant-facing recommendations for the Dispute Outcome View.
 *
 * Authored by RiskOps. Each entry has:
 *   - title + body: copy that renders verbatim (voice rules: softer
 *     evidence language, single-sentence bodies, no em-dashes). Wrapped in
 *     `__()` so the strings are translatable.
 *   - urgency: drives title color and section grouping.
 *     `positive` (green) → "What's working well" section.
 *     `critical` (red) + `tip` (orange) → "What could help next time".
 *   - when: predicates the runtime helper checks against the dispute.
 *     `requireProvided` / `requireMissing` are count predicates over a
 *     key set (`min`/`max` inclusive; see `FieldCountPredicate` and
 *     `matchesCount()` for the default semantics, including `max`-only).
 *   - link (optional): inline action link. External destinations are
 *     absolute `https://` URLs; wp-admin destinations are built with
 *     `getAdminUrl()` / `addQueryArgs()` here so they resolve correctly on
 *     subdirectory installs. The component renders the two link kinds
 *     differently (see `dispute-recommendations/index.tsx`).
 *   - suppressOtherCriticals: catch-all "no evidence" entry uses this to
 *     hide other critical entries on the same dispute.
 *
 * Clusters 13 (response-time timing) is intentionally deferred until
 * `dispute.evidence_details.submitted_at` reaches the client.
 *
 * Cluster 6 references two cancellation fields rather than three. The
 * RiskOps catalog originally listed `cancellation_policy_disclosure`
 * alongside `cancellation_policy` and `cancellation_rebuttal`, but the
 * dispute response wizard surfaces only the latter two — so coaching the
 * merchant on a field they can't actually provide creates confusion.
 * Follow-up: add `cancellation_policy_disclosure` to the wizard, then
 * widen this cluster back to three fields.
 */

// eslint-disable-next-line @typescript-eslint/naming-convention -- This is a constant object.
export const RECOMMENDATIONS_CATALOG: Recommendation[] = [
	// ============ CLUSTER 1: shipping evidence ============
	{
		id: 'c1-shipping-evidence-strong',
		urgency: 'positive',
		title: __( 'Strong shipping evidence', 'woocommerce-payments' ),
		body: __(
			'Your tracking number and carrier info helped demonstrate delivery.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'product_not_received' ],
			productTypeIn: [ 'physical_product' ],
			requireProvided: {
				keys: [ 'shipping_tracking_number', 'shipping_carrier' ],
				min: 2,
			},
		},
	},
	{
		id: 'c1-shipping-tracking-add',
		urgency: 'critical',
		title: __(
			'Add shipping tracking for every order',
			'woocommerce-payments'
		),
		body: __(
			'Shipping tracking that shows delivery is among the strongest evidence for product-not-received disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [ 'product_not_received' ],
			productTypeIn: [ 'physical_product' ],
			requireMissing: { keys: [ 'shipping_tracking_number' ] },
		},
		link: {
			label: __( 'Set up shipping tracking', 'woocommerce-payments' ),
			href: 'https://woocommerce.com/products/shipment-tracking/',
		},
	},
	{
		id: 'c1-shipping-evidence-strengthen',
		urgency: 'tip',
		title: __(
			'Strengthen your shipping evidence',
			'woocommerce-payments'
		),
		body: __(
			'Adding carrier info or a delivery date alongside tracking gives a fuller picture for physical goods disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'product_not_received' ],
			productTypeIn: [ 'physical_product' ],
			requireProvided: { keys: [ 'shipping_tracking_number' ] },
			requireMissing: {
				keys: [
					'shipping_carrier',
					'shipping_date',
					'shipping_address',
					'shipping_documentation',
				],
			},
		},
		link: {
			label: __( 'Configure shipping options', 'woocommerce-payments' ),
			href: getAdminUrl( { page: 'wc-settings', tab: 'shipping' } ),
		},
	},

	// ============ CLUSTER 2: receipt ============
	{
		id: 'c2-receipt-provided',
		urgency: 'positive',
		title: __( 'Clear proof of purchase', 'woocommerce-payments' ),
		body: __(
			'A receipt added support to your defense.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [
				'general',
				'product_not_received',
				'duplicate',
				'credit_not_processed',
				'product_unacceptable',
			],
			requireProvided: { keys: [ 'receipt' ] },
		},
	},
	{
		id: 'c2-receipt-include',
		urgency: 'critical',
		title: __( 'Include the order receipt', 'woocommerce-payments' ),
		body: __(
			'A clear receipt is one of the more reliable pieces of evidence across dispute types.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [
				'general',
				'product_not_received',
				'duplicate',
				'credit_not_processed',
				'product_unacceptable',
			],
			requireMissing: { keys: [ 'receipt' ] },
		},
	},
	{
		id: 'c2-receipt-include-tip',
		urgency: 'tip',
		title: __(
			'Include a receipt with your evidence',
			'woocommerce-payments'
		),
		body: __(
			'Receipts tend to strengthen evidence packages across most dispute types, even when you win without one.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [
				'general',
				'product_not_received',
				'duplicate',
				'credit_not_processed',
				'product_unacceptable',
			],
			requireMissing: { keys: [ 'receipt' ] },
		},
	},

	// ============ CLUSTER 3: customer communication ============
	{
		id: 'c3-communication-provided',
		urgency: 'positive',
		title: __( 'Customer communication on file', 'woocommerce-payments' ),
		body: __(
			'Your correspondence with the customer added helpful context.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [
				'product_not_received',
				'fraudulent',
				'product_unacceptable',
				'credit_not_processed',
				'general',
			],
			requireProvided: { keys: [ 'customer_communication' ] },
		},
	},
	{
		id: 'c3-communication-include',
		urgency: 'critical',
		title: __( 'Include customer correspondence', 'woocommerce-payments' ),
		body: __(
			'Emails or messages with the customer can add helpful context to your defense.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [
				'product_not_received',
				'fraudulent',
				'product_unacceptable',
				'credit_not_processed',
				'general',
			],
			requireMissing: { keys: [ 'customer_communication' ] },
		},
	},
	{
		id: 'c3-communication-consider',
		urgency: 'tip',
		title: __(
			'Consider attaching customer correspondence',
			'woocommerce-payments'
		),
		body: __(
			'Relevant correspondence tends to help across dispute types where the conversation supports your position.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [
				'product_not_received',
				'fraudulent',
				'product_unacceptable',
				'credit_not_processed',
				'general',
			],
			requireMissing: { keys: [ 'customer_communication' ] },
		},
	},

	// ============ CLUSTER 4: refund refusal explanation ============
	{
		id: 'c4-refund-refusal-provided',
		urgency: 'positive',
		title: __(
			'Clear explanation for the refund decision',
			'woocommerce-payments'
		),
		body: __(
			"Your written explanation of why the refund wasn't owed gave context for your decision.",
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'product_unacceptable', 'credit_not_processed' ],
			requireProvided: { keys: [ 'refund_refusal_explanation' ] },
		},
	},
	{
		id: 'c4-refund-refusal-explain',
		urgency: 'critical',
		title: __( 'Explain the refund decision', 'woocommerce-payments' ),
		body: __(
			"A written explanation of why the refund wasn't owed helps clarify your reasoning on refund-related disputes.",
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [ 'product_unacceptable', 'credit_not_processed' ],
			requireMissing: { keys: [ 'refund_refusal_explanation' ] },
		},
	},
	{
		id: 'c4-refund-refusal-add',
		urgency: 'tip',
		title: __(
			'Add an explanation for refund decisions',
			'woocommerce-payments'
		),
		body: __(
			'Adding a written explanation for refund decisions tends to round out the evidence on refund disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'product_unacceptable', 'credit_not_processed' ],
			requireMissing: { keys: [ 'refund_refusal_explanation' ] },
		},
	},

	// ============ CLUSTER 5: refund policy ============
	{
		id: 'c5-refund-policy-provided',
		urgency: 'positive',
		title: __( 'Clear refund policy', 'woocommerce-payments' ),
		body: __(
			'Your refund policy gave context for how returns work in your store.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [
				'product_unacceptable',
				'credit_not_processed',
				'duplicate',
				'subscription_canceled',
			],
			requireProvided: { keys: [ 'refund_policy' ] },
		},
	},
	{
		id: 'c5-refund-policy-publish-won',
		urgency: 'tip',
		title: __( 'Publish a clear refund policy', 'woocommerce-payments' ),
		body: __(
			'A published refund policy gives customers context for how returns work in your store, and ties together your other refund-related evidence.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [
				'product_unacceptable',
				'credit_not_processed',
				'duplicate',
				'subscription_canceled',
			],
			requireMissing: { keys: [ 'refund_policy' ] },
		},
		link: {
			// TODO(wiring): deep-link to the refund policy page itself once
			// the WC settings page id reaches the client.
			label: __( 'Edit your refund policy page', 'woocommerce-payments' ),
			href: getAdminUrl( { page: 'wc-settings', tab: 'advanced' } ),
		},
	},
	{
		id: 'c5-refund-policy-publish-lost',
		urgency: 'tip',
		title: __( 'Publish a clear refund policy', 'woocommerce-payments' ),
		body: __(
			'A published refund policy gives customers context for how returns work in your store, and ties together your other refund-related evidence.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [
				'product_unacceptable',
				'credit_not_processed',
				'duplicate',
				'subscription_canceled',
			],
			requireMissing: { keys: [ 'refund_policy' ] },
		},
		link: {
			label: __( 'Edit your refund policy page', 'woocommerce-payments' ),
			href: getAdminUrl( { page: 'wc-settings', tab: 'advanced' } ),
		},
	},

	// ============ CLUSTER 6: cancellation policy (2 of 2 fields; see header note) ============
	{
		id: 'c6-cancellation-provided',
		urgency: 'positive',
		title: __( 'Cancellation policy on record', 'woocommerce-payments' ),
		body: __(
			'Your cancellation policy and supporting records helped clarify the subscription terms.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'subscription_canceled' ],
			requireProvided: {
				keys: [ 'cancellation_policy', 'cancellation_rebuttal' ],
				min: 2,
			},
		},
	},
	{
		id: 'c6-cancellation-document',
		urgency: 'critical',
		title: __( 'Document your cancellation terms', 'woocommerce-payments' ),
		body: __(
			'Cancellation policies, terms shown at checkout, and cancellation records are central to subscription disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [ 'subscription_canceled' ],
			requireMissing: {
				keys: [ 'cancellation_policy', 'cancellation_rebuttal' ],
				min: 2,
			},
		},
		link: {
			label: __(
				'Configure subscription settings',
				'woocommerce-payments'
			),
			href: getAdminUrl( {
				page: 'wc-settings',
				tab: 'subscriptions',
			} ),
		},
	},
	{
		id: 'c6-cancellation-add-exactly-one',
		urgency: 'tip',
		title: __( 'Add cancellation documentation', 'woocommerce-payments' ),
		body: __(
			'Documented cancellation terms and supporting records help defend subscription disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'subscription_canceled' ],
			requireProvided: {
				keys: [ 'cancellation_policy', 'cancellation_rebuttal' ],
				min: 1,
				max: 1,
			},
		},
		link: {
			label: __(
				'Configure subscription settings',
				'woocommerce-payments'
			),
			href: getAdminUrl( {
				page: 'wc-settings',
				tab: 'subscriptions',
			} ),
		},
	},
	{
		id: 'c6-cancellation-add-exactly-one-lost',
		urgency: 'tip',
		title: __( 'Add cancellation documentation', 'woocommerce-payments' ),
		body: __(
			'Documented cancellation terms and supporting records help defend subscription disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [ 'subscription_canceled' ],
			requireProvided: {
				keys: [ 'cancellation_policy', 'cancellation_rebuttal' ],
				min: 1,
				max: 1,
			},
		},
		link: {
			label: __(
				'Configure subscription settings',
				'woocommerce-payments'
			),
			href: getAdminUrl( {
				page: 'wc-settings',
				tab: 'subscriptions',
			} ),
		},
	},
	{
		id: 'c6-cancellation-add-none-on-won',
		urgency: 'tip',
		title: __( 'Add cancellation documentation', 'woocommerce-payments' ),
		body: __(
			'Documented cancellation terms and supporting records help defend subscription disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'subscription_canceled' ],
			requireMissing: {
				keys: [ 'cancellation_policy', 'cancellation_rebuttal' ],
				min: 2,
			},
		},
		link: {
			label: __(
				'Configure subscription settings',
				'woocommerce-payments'
			),
			href: getAdminUrl( {
				page: 'wc-settings',
				tab: 'subscriptions',
			} ),
		},
	},

	// ============ CLUSTER 7: duplicate charge ============
	{
		id: 'c7-duplicate-charge-explained',
		urgency: 'positive',
		title: __( 'Two charges, clearly explained', 'woocommerce-payments' ),
		body: __(
			'An explanation alongside documentation made the two charges easier to distinguish.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'duplicate' ],
			requireProvided: {
				keys: [
					'duplicate_charge_explanation',
					'duplicate_charge_documentation',
				],
				min: 2,
			},
		},
	},
	{
		id: 'c7-duplicate-charge-explain',
		urgency: 'critical',
		title: __(
			'Explain and document the duplicate charge',
			'woocommerce-payments'
		),
		body: __(
			'A clear explanation alongside documentation distinguishing the charges is central evidence for duplicate disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [ 'duplicate' ],
			requireMissing: {
				keys: [
					'duplicate_charge_explanation',
					'duplicate_charge_documentation',
				],
				min: 2,
			},
		},
	},
	{
		id: 'c7-duplicate-charge-missing-piece-won',
		urgency: 'tip',
		title: __(
			'Add the missing duplicate-charge piece',
			'woocommerce-payments'
		),
		body: __(
			'An explanation and documentation together help build out the defense for duplicate disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'duplicate' ],
			requireProvided: {
				keys: [
					'duplicate_charge_explanation',
					'duplicate_charge_documentation',
				],
				min: 1,
				max: 1,
			},
		},
	},
	{
		id: 'c7-duplicate-charge-missing-piece-lost',
		urgency: 'tip',
		title: __(
			'Add the missing duplicate-charge piece',
			'woocommerce-payments'
		),
		body: __(
			'An explanation and documentation together help build out the defense for duplicate disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [ 'duplicate' ],
			requireProvided: {
				keys: [
					'duplicate_charge_explanation',
					'duplicate_charge_documentation',
				],
				min: 1,
				max: 1,
			},
		},
	},

	// ============ CLUSTER 8: service date (non-physical only) ============
	// Scoped to non-physical product types because the wizard collects
	// `shipping_date` (not `service_date`) for physical_product fraudulent
	// disputes. Coaching a physical-product merchant on `service_date` asks
	// them to populate a field the wizard never surfaces for their type.
	// Mirrors DISPUTE_HIGH_IMPACT_FIELDS, which already encodes the same
	// distinction for fraudulent. Follow-up: separate shipping_date
	// coaching for fraudulent + physical_product if RiskOps wants it.
	{
		id: 'c8-service-date-provided',
		urgency: 'positive',
		title: __( 'Service date on record', 'woocommerce-payments' ),
		body: __(
			'Including the service date helped tie the transaction to a verifiable event.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'fraudulent' ],
			productTypeIn: [
				'digital_product_or_service',
				'offline_service',
				'event',
				'booking_reservation',
				'multiple',
				'other',
			],
			requireProvided: { keys: [ 'service_date' ] },
		},
	},
	{
		id: 'c8-service-date-include',
		urgency: 'critical',
		title: __( 'Include the service date', 'woocommerce-payments' ),
		body: __(
			'The service date ties the transaction to a verifiable event, which can help defend fraud disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [ 'fraudulent' ],
			productTypeIn: [
				'digital_product_or_service',
				'offline_service',
				'event',
				'booking_reservation',
				'multiple',
				'other',
			],
			requireMissing: { keys: [ 'service_date' ] },
		},
	},
	{
		id: 'c8-service-date-document',
		urgency: 'tip',
		title: __( 'Document the service date', 'woocommerce-payments' ),
		body: __(
			'Documenting the service date helps tie transactions to verifiable events in fraud disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'fraudulent' ],
			productTypeIn: [
				'digital_product_or_service',
				'offline_service',
				'event',
				'booking_reservation',
				'multiple',
				'other',
			],
			requireMissing: { keys: [ 'service_date' ] },
		},
	},

	// ============ CLUSTER 8b: shipping date (fraudulent + physical only) ============
	// Parallel to Cluster 8, keyed off `shipping_date` (the field the wizard
	// collects for physical_product fraudulent disputes) instead of
	// `service_date`. Two variants only (no Critical): per RiskOps review,
	// the shipping date doesn't prove the true cardholder made the purchase,
	// it just ties the order to a verifiable event at the cardholder's
	// address. Worth surfacing, not worth a Critical.
	{
		id: 'c8b-shipping-date-provided',
		urgency: 'positive',
		title: __( 'Shipping date on record', 'woocommerce-payments' ),
		body: __(
			"Documenting the shipping date tied this order to a verifiable event at the cardholder's address.",
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'fraudulent' ],
			productTypeIn: [ 'physical_product' ],
			requireProvided: { keys: [ 'shipping_date' ] },
		},
	},
	{
		id: 'c8b-shipping-date-document',
		urgency: 'tip',
		title: __( 'Document the shipping date', 'woocommerce-payments' ),
		body: __(
			"Including the shipping date ties an order to a verifiable event at the cardholder's address.",
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'fraudulent' ],
			productTypeIn: [ 'physical_product' ],
			requireMissing: { keys: [ 'shipping_date' ] },
		},
	},
	{
		id: 'c8b-shipping-date-document-lost',
		urgency: 'tip',
		title: __( 'Document the shipping date', 'woocommerce-payments' ),
		body: __(
			"Including the shipping date ties an order to a verifiable event at the cardholder's address.",
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [ 'fraudulent' ],
			productTypeIn: [ 'physical_product' ],
			requireMissing: { keys: [ 'shipping_date' ] },
		},
	},

	// ============ CLUSTER 9: service documentation ============
	{
		id: 'c9-service-documentation-provided',
		urgency: 'positive',
		title: __( 'Proof of service delivered', 'woocommerce-payments' ),
		body: __(
			'Service documentation helped establish that the service was delivered.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [
				'product_not_received',
				'product_unacceptable',
				'fraudulent',
			],
			productTypeIn: [
				'offline_service',
				'event',
				'booking_reservation',
			],
			requireProvided: { keys: [ 'service_documentation' ] },
		},
	},
	{
		id: 'c9-service-documentation-attach-won',
		urgency: 'tip',
		title: __(
			'Attach proof the service was delivered',
			'woocommerce-payments'
		),
		body: __(
			'Records showing the event, reservation, or service was completed help defend service-based disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [
				'product_not_received',
				'product_unacceptable',
				'fraudulent',
			],
			productTypeIn: [
				'offline_service',
				'event',
				'booking_reservation',
			],
			requireMissing: { keys: [ 'service_documentation' ] },
		},
	},
	{
		id: 'c9-service-documentation-attach-lost',
		urgency: 'tip',
		title: __(
			'Attach proof the service was delivered',
			'woocommerce-payments'
		),
		body: __(
			'Records showing the event, reservation, or service was completed help defend service-based disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [
				'product_not_received',
				'product_unacceptable',
				'fraudulent',
			],
			productTypeIn: [
				'offline_service',
				'event',
				'booking_reservation',
			],
			requireMissing: { keys: [ 'service_documentation' ] },
		},
	},

	// ============ CLUSTER 10: access activity log ============
	{
		id: 'c10-access-log-provided',
		urgency: 'positive',
		title: __( 'Usage records on file', 'woocommerce-payments' ),
		body: __(
			'Your access logs helped show the customer used the product.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [
				'product_unacceptable',
				'fraudulent',
				'product_not_received',
			],
			productTypeIn: [ 'digital_product_or_service' ],
			requireProvided: { keys: [ 'access_activity_log' ] },
		},
	},
	{
		id: 'c10-access-log-include',
		urgency: 'critical',
		title: __(
			'Include usage records for digital products',
			'woocommerce-payments'
		),
		body: __(
			'Access logs showing the customer used the product are central evidence for digital product-quality disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [ 'product_unacceptable' ],
			productTypeIn: [ 'digital_product_or_service' ],
			requireMissing: { keys: [ 'access_activity_log' ] },
		},
	},
	{
		id: 'c10-access-log-add',
		urgency: 'tip',
		title: __( 'Add access logs to your evidence', 'woocommerce-payments' ),
		body: __(
			'Usage records help defend digital product disputes by showing the customer accessed the product.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [
				'product_unacceptable',
				'fraudulent',
				'product_not_received',
			],
			productTypeIn: [ 'digital_product_or_service' ],
			requireMissing: { keys: [ 'access_activity_log' ] },
		},
	},

	// ============ CLUSTER 11a: customer signature (PNR + Physical) ============
	{
		id: 'c11a-signature-provided',
		urgency: 'positive',
		title: __( 'Signed delivery proof', 'woocommerce-payments' ),
		body: __(
			'Signed delivery records helped confirm the customer received the product.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'product_not_received' ],
			productTypeIn: [ 'physical_product' ],
			requireProvided: { keys: [ 'customer_signature' ] },
		},
	},
	{
		id: 'c11a-signature-include',
		urgency: 'critical',
		title: __( 'Include signed delivery proof', 'woocommerce-payments' ),
		body: __(
			'Signed proof of delivery confirms the customer received the product, which is central to product-not-received disputes.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [ 'product_not_received' ],
			productTypeIn: [ 'physical_product' ],
			requireMissing: { keys: [ 'customer_signature' ] },
		},
		link: {
			label: __(
				'Set up shipping with delivery confirmation',
				'woocommerce-payments'
			),
			href: 'https://woocommerce.com/products/shipment-tracking/',
		},
	},
	{
		id: 'c11a-signature-add',
		urgency: 'tip',
		title: __( 'Add signed delivery proof', 'woocommerce-payments' ),
		body: __(
			'Where signed delivery records are available, they tend to strengthen physical goods dispute evidence.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'product_not_received' ],
			productTypeIn: [ 'physical_product' ],
			requireMissing: { keys: [ 'customer_signature' ] },
		},
		link: {
			label: __(
				'Set up shipping with delivery confirmation',
				'woocommerce-payments'
			),
			href: 'https://woocommerce.com/products/shipment-tracking/',
		},
	},

	// ============ CLUSTER 11b: customer signature (CNP + Physical) ============
	{
		id: 'c11b-signature-provided',
		urgency: 'positive',
		title: __(
			'Signed delivery proof (refund case)',
			'woocommerce-payments'
		),
		body: __(
			'Signed delivery proof from the original order showed the customer received the product before disputing the refund.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'credit_not_processed' ],
			productTypeIn: [ 'physical_product' ],
			requireProvided: { keys: [ 'customer_signature' ] },
		},
	},
	{
		id: 'c11b-signature-include',
		urgency: 'critical',
		title: __(
			'Include signed delivery proof from the original order',
			'woocommerce-payments'
		),
		body: __(
			'For refund disputes on physical goods, signed delivery proof from the original order establishes the customer received the product before the dispute.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [ 'credit_not_processed' ],
			productTypeIn: [ 'physical_product' ],
			requireMissing: { keys: [ 'customer_signature' ] },
		},
		link: {
			label: __(
				'Set up shipping with delivery confirmation',
				'woocommerce-payments'
			),
			href: 'https://woocommerce.com/products/shipment-tracking/',
		},
	},
	{
		id: 'c11b-signature-consider',
		urgency: 'tip',
		title: __(
			'Consider attaching signed delivery proof',
			'woocommerce-payments'
		),
		body: __(
			'Signed delivery records from the original order can support refund disputes on physical goods.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'credit_not_processed' ],
			productTypeIn: [ 'physical_product' ],
			requireMissing: { keys: [ 'customer_signature' ] },
		},
		link: {
			label: __(
				'Set up shipping with delivery confirmation',
				'woocommerce-payments'
			),
			href: 'https://woocommerce.com/products/shipment-tracking/',
		},
	},

	// ============ CLUSTER 12: cover letter (Tip only) ============
	// Fires when the merchant cleared the auto-generated cover letter.
	// Detection here is "uncategorized_text empty" — the slice-4 follow-up
	// surfaces the same gap in the Evidence Submitted list.
	{
		id: 'c12-cover-letter-include',
		urgency: 'tip',
		title: __(
			'Include a cover letter with your evidence',
			'woocommerce-payments'
		),
		body: __(
			'The cover letter is how you introduce your case and tie your evidence together.',
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [
				'product_not_received',
				'fraudulent',
				'product_unacceptable',
				'credit_not_processed',
				'duplicate',
				'subscription_canceled',
				'general',
			],
			requireMissing: { keys: [ 'uncategorized_text' ] },
		},
	},

	// ============ CLUSTER 13: response-time timing ============
	// Deferred until `dispute.evidence_details.submitted_at` reaches the
	// client. Two entries (positive on quick responses, tip on slow ones)
	// add back when the predicate language gains a timing clause and the
	// backend exposes the field.

	// ============ CLUSTER 14: prior good history (Tip only) ============
	{
		id: 'c14-prior-history-mention',
		urgency: 'tip',
		title: __(
			'Mention prior good history with the customer',
			'woocommerce-payments'
		),
		body: __(
			"Where possible, mention in your cover letter the customer's prior successful orders, and attach proof from their order history.",
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [ 'fraudulent' ],
		},
		link: {
			// TODO(wiring): filter to this customer's orders once
			// dispute.order.customer reaches the component.
			label: __(
				"View customer's order history",
				'woocommerce-payments'
			),
			href: addQueryArgs( 'edit.php', { post_type: 'shop_order' } ),
		},
	},

	// ============ CLUSTER 15: no evidence (Critical, suppresses others) ============
	{
		id: 'c15-no-evidence-submit',
		urgency: 'critical',
		title: __(
			'Submit evidence with your dispute response',
			'woocommerce-payments'
		),
		body: __(
			"Without evidence to weigh against the customer's claim, disputes generally default in the customer's favor.",
			'woocommerce-payments'
		),
		when: {
			outcome: 'could_help',
			reasonIn: [
				'product_not_received',
				'fraudulent',
				'product_unacceptable',
				'credit_not_processed',
				'duplicate',
				'subscription_canceled',
				'general',
				'bank_cannot_process',
				'check_returned',
				'customer_initiated',
				'debit_not_authorized',
				'incorrect_account_details',
				'insufficient_funds',
				'noncompliant',
				'unrecognized',
			],
			// Predicate: at least one wizard-submittable evidence field is
			// provided. When ALL are missing, the entry fires (max:0) and its
			// suppression rule hides every other critical entry on the dispute.
			// The key set must cover every field the response wizard can
			// collect — otherwise the entry can fire (and suppress criticals)
			// while meaningful evidence sits in an unlisted field.
			requireProvided: {
				keys: [
					'customer_communication',
					'receipt',
					'shipping_documentation',
					'shipping_tracking_number',
					'shipping_carrier',
					'shipping_date',
					'shipping_address',
					'service_documentation',
					'service_date',
					'access_activity_log',
					'duplicate_charge_documentation',
					'duplicate_charge_explanation',
					'refund_policy',
					'refund_refusal_explanation',
					'cancellation_policy',
					'cancellation_rebuttal',
					'customer_signature',
					'customer_purchase_ip',
					'uncategorized_file',
					'uncategorized_text',
				],
				max: 0,
			},
		},
		link: {
			label: __( 'Learn how to defend disputes', 'woocommerce-payments' ),
			href: 'https://woocommerce.com/document/managing-payment-disputes/',
		},
		suppressOtherCriticals: true,
	},
];
