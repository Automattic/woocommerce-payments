/**
 * Internal dependencies
 */
import type { Recommendation } from './types';

/**
 * Catalog of merchant-facing recommendations for the Dispute Outcome View.
 *
 * Authored by RiskOps. Each entry has:
 *   - title + body: copy that renders verbatim (voice rules: softer
 *     evidence language, single-sentence bodies, no em-dashes).
 *   - urgency: drives title color and section grouping.
 *     `positive` (green) → "What's working well" section.
 *     `critical` (red) + `tip` (orange) → "What could help next time".
 *   - when: predicates the runtime helper checks against the dispute.
 *     `requireProvided` / `requireMissing` are count predicates over a
 *     key set (`min`/`max` inclusive; defaults: min=1, max=keys.length).
 *   - link (optional): inline action link. `href` can contain `{token}`
 *     placeholders; the component substitutes from dispute context and
 *     falls back to `fallbackHref` if a token can't be resolved.
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
		title: 'Strong shipping evidence',
		body: 'Your tracking number and carrier info helped demonstrate delivery.',
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
		title: 'Add shipping tracking for every order',
		body: 'Shipping tracking that shows delivery is among the strongest evidence for product-not-received disputes.',
		when: {
			outcome: 'could_help',
			reasonIn: [ 'product_not_received' ],
			productTypeIn: [ 'physical_product' ],
			requireMissing: { keys: [ 'shipping_tracking_number' ] },
		},
		link: {
			label: 'Set up shipping tracking',
			href: 'https://woocommerce.com/products/shipment-tracking/',
		},
	},
	{
		id: 'c1-shipping-evidence-strengthen',
		urgency: 'tip',
		title: 'Strengthen your shipping evidence',
		body: 'Adding carrier info or a delivery date alongside tracking gives a fuller picture for physical goods disputes.',
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
			label: 'Configure shipping options',
			href: '/wp-admin/admin.php?page=wc-settings&tab=shipping',
		},
	},

	// ============ CLUSTER 2: receipt ============
	{
		id: 'c2-receipt-provided',
		urgency: 'positive',
		title: 'Clear proof of purchase',
		body: 'A receipt added support to your defense.',
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
		title: 'Include the order receipt',
		body: 'A clear receipt is one of the more reliable pieces of evidence across dispute types.',
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
		title: 'Include a receipt with your evidence',
		body: 'Receipts tend to strengthen evidence packages across most dispute types, even when you win without one.',
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
		title: 'Customer communication on file',
		body: 'Your correspondence with the customer added helpful context.',
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
		title: 'Include customer correspondence',
		body: 'Emails or messages with the customer can add helpful context to your defense.',
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
		title: 'Consider attaching customer correspondence',
		body: 'Relevant correspondence tends to help across dispute types where the conversation supports your position.',
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
		title: 'Clear explanation for the refund decision',
		body: "Your written explanation of why the refund wasn't owed gave context for your decision.",
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'product_unacceptable', 'credit_not_processed' ],
			requireProvided: { keys: [ 'refund_refusal_explanation' ] },
		},
	},
	{
		id: 'c4-refund-refusal-explain',
		urgency: 'critical',
		title: 'Explain the refund decision',
		body: "A written explanation of why the refund wasn't owed helps clarify your reasoning on refund-related disputes.",
		when: {
			outcome: 'could_help',
			reasonIn: [ 'product_unacceptable', 'credit_not_processed' ],
			requireMissing: { keys: [ 'refund_refusal_explanation' ] },
		},
	},
	{
		id: 'c4-refund-refusal-add',
		urgency: 'tip',
		title: 'Add an explanation for refund decisions',
		body: 'Adding a written explanation for refund decisions tends to round out the evidence on refund disputes.',
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
		title: 'Clear refund policy',
		body: 'Your refund policy gave context for how returns work in your store.',
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
		title: 'Publish a clear refund policy',
		body: 'A published refund policy gives customers context for how returns work in your store, and ties together your other refund-related evidence.',
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
			// TODO(wiring): substitute {refund_policy_page_id} when WC settings reach the client.
			label: 'Edit your refund policy page',
			href: '/wp-admin/admin.php?page=wc-settings&tab=advanced',
		},
	},
	{
		id: 'c5-refund-policy-publish-lost',
		urgency: 'tip',
		title: 'Publish a clear refund policy',
		body: 'A published refund policy gives customers context for how returns work in your store, and ties together your other refund-related evidence.',
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
			label: 'Edit your refund policy page',
			href: '/wp-admin/admin.php?page=wc-settings&tab=advanced',
		},
	},

	// ============ CLUSTER 6: cancellation policy (2 of 2 fields; see header note) ============
	{
		id: 'c6-cancellation-provided',
		urgency: 'positive',
		title: 'Cancellation policy on record',
		body: 'Your cancellation policy and supporting records helped clarify the subscription terms.',
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
		title: 'Document your cancellation terms',
		body: 'Cancellation policies, terms shown at checkout, and cancellation records are central to subscription disputes.',
		when: {
			outcome: 'could_help',
			reasonIn: [ 'subscription_canceled' ],
			requireMissing: {
				keys: [ 'cancellation_policy', 'cancellation_rebuttal' ],
				min: 2,
			},
		},
		link: {
			label: 'Configure subscription settings',
			href: '/wp-admin/admin.php?page=wc-settings&tab=subscriptions',
		},
	},
	{
		id: 'c6-cancellation-add-exactly-one',
		urgency: 'tip',
		title: 'Add cancellation documentation',
		body: 'Documented cancellation terms and supporting records help defend subscription disputes.',
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
			label: 'Configure subscription settings',
			href: '/wp-admin/admin.php?page=wc-settings&tab=subscriptions',
		},
	},
	{
		id: 'c6-cancellation-add-exactly-one-lost',
		urgency: 'tip',
		title: 'Add cancellation documentation',
		body: 'Documented cancellation terms and supporting records help defend subscription disputes.',
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
			label: 'Configure subscription settings',
			href: '/wp-admin/admin.php?page=wc-settings&tab=subscriptions',
		},
	},
	{
		id: 'c6-cancellation-add-none-on-won',
		urgency: 'tip',
		title: 'Add cancellation documentation',
		body: 'Documented cancellation terms and supporting records help defend subscription disputes.',
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'subscription_canceled' ],
			requireMissing: {
				keys: [ 'cancellation_policy', 'cancellation_rebuttal' ],
				min: 2,
			},
		},
		link: {
			label: 'Configure subscription settings',
			href: '/wp-admin/admin.php?page=wc-settings&tab=subscriptions',
		},
	},

	// ============ CLUSTER 7: duplicate charge ============
	{
		id: 'c7-duplicate-charge-explained',
		urgency: 'positive',
		title: 'Two charges, clearly explained',
		body: 'An explanation alongside documentation made the two charges easier to distinguish.',
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
		title: 'Explain and document the duplicate charge',
		body: 'A clear explanation alongside documentation distinguishing the charges is central evidence for duplicate disputes.',
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
		title: 'Add the missing duplicate-charge piece',
		body: 'An explanation and documentation together help build out the defense for duplicate disputes.',
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
		title: 'Add the missing duplicate-charge piece',
		body: 'An explanation and documentation together help build out the defense for duplicate disputes.',
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
		title: 'Service date on record',
		body: 'Including the service date helped tie the transaction to a verifiable event.',
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
		title: 'Include the service date',
		body: 'The service date ties the transaction to a verifiable event, which can help defend fraud disputes.',
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
		title: 'Document the service date',
		body: 'Documenting the service date helps tie transactions to verifiable events in fraud disputes.',
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

	// ============ CLUSTER 9: service documentation ============
	{
		id: 'c9-service-documentation-provided',
		urgency: 'positive',
		title: 'Proof of service delivered',
		body: 'Service documentation helped establish that the service was delivered.',
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
		title: 'Attach proof the service was delivered',
		body: 'Records showing the event, reservation, or service was completed help defend service-based disputes.',
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
		title: 'Attach proof the service was delivered',
		body: 'Records showing the event, reservation, or service was completed help defend service-based disputes.',
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
		title: 'Usage records on file',
		body: 'Your access logs helped show the customer used the product.',
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
		title: 'Include usage records for digital products',
		body: 'Access logs showing the customer used the product are central evidence for digital product-quality disputes.',
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
		title: 'Add access logs to your evidence',
		body: 'Usage records help defend digital product disputes by showing the customer accessed the product.',
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
		title: 'Signed delivery proof',
		body: 'Signed delivery records helped confirm the customer received the product.',
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
		title: 'Include signed delivery proof',
		body: 'Signed proof of delivery confirms the customer received the product, which is central to product-not-received disputes.',
		when: {
			outcome: 'could_help',
			reasonIn: [ 'product_not_received' ],
			productTypeIn: [ 'physical_product' ],
			requireMissing: { keys: [ 'customer_signature' ] },
		},
		link: {
			label: 'Set up shipping with delivery confirmation',
			href: 'https://woocommerce.com/products/shipment-tracking/',
		},
	},
	{
		id: 'c11a-signature-add',
		urgency: 'tip',
		title: 'Add signed delivery proof',
		body: 'Where signed delivery records are available, they tend to strengthen physical goods dispute evidence.',
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'product_not_received' ],
			productTypeIn: [ 'physical_product' ],
			requireMissing: { keys: [ 'customer_signature' ] },
		},
		link: {
			label: 'Set up shipping with delivery confirmation',
			href: 'https://woocommerce.com/products/shipment-tracking/',
		},
	},

	// ============ CLUSTER 11b: customer signature (CNP + Physical) ============
	{
		id: 'c11b-signature-provided',
		urgency: 'positive',
		title: 'Signed delivery proof (refund case)',
		body: 'Signed delivery proof from the original order showed the customer received the product before disputing the refund.',
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
		title: 'Include signed delivery proof from the original order',
		body: 'For refund disputes on physical goods, signed delivery proof from the original order establishes the customer received the product before the dispute.',
		when: {
			outcome: 'could_help',
			reasonIn: [ 'credit_not_processed' ],
			productTypeIn: [ 'physical_product' ],
			requireMissing: { keys: [ 'customer_signature' ] },
		},
		link: {
			label: 'Set up shipping with delivery confirmation',
			href: 'https://woocommerce.com/products/shipment-tracking/',
		},
	},
	{
		id: 'c11b-signature-consider',
		urgency: 'tip',
		title: 'Consider attaching signed delivery proof',
		body: 'Signed delivery records from the original order can support refund disputes on physical goods.',
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'credit_not_processed' ],
			productTypeIn: [ 'physical_product' ],
			requireMissing: { keys: [ 'customer_signature' ] },
		},
		link: {
			label: 'Set up shipping with delivery confirmation',
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
		title: 'Include a cover letter with your evidence',
		body: 'The cover letter is how you introduce your case and tie your evidence together.',
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
		title: 'Mention prior good history with the customer',
		body: "Where possible, mention in your cover letter the customer's prior successful orders, and attach proof from their order history.",
		when: {
			outcome: 'could_help',
			reasonIn: [ 'fraudulent' ],
		},
		link: {
			// TODO(wiring): substitute {customer_id} when dispute.order.customer reaches the component.
			label: "View customer's order history",
			href: '/wp-admin/edit.php?post_type=shop_order',
		},
	},

	// ============ CLUSTER 15: no evidence (Critical, suppresses others) ============
	{
		id: 'c15-no-evidence-submit',
		urgency: 'critical',
		title: 'Submit evidence with your dispute response',
		body: "Without evidence to weigh against the customer's claim, disputes generally default in the customer's favor.",
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
			// Predicate: at least one of these key evidence fields is provided.
			// When ALL are missing, the entry fires (max:0) and its suppression
			// rule hides every other critical entry on the dispute.
			requireProvided: {
				keys: [
					'customer_communication',
					'receipt',
					'shipping_documentation',
					'shipping_tracking_number',
					'service_documentation',
					'access_activity_log',
					'duplicate_charge_documentation',
					'duplicate_charge_explanation',
					'refund_policy',
					'refund_refusal_explanation',
					'cancellation_policy',
					'cancellation_rebuttal',
					'customer_signature',
					'uncategorized_file',
					'uncategorized_text',
				],
				max: 0,
			},
		},
		link: {
			label: 'Learn how to defend disputes',
			href: 'https://woocommerce.com/document/woopayments/fraud-and-disputes/preventing-disputes/#be-prepared',
		},
		suppressOtherCriticals: true,
	},
];
