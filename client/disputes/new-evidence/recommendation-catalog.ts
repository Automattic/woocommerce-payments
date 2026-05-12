/**
 * Internal dependencies
 */
import type { Recommendation } from './types';

/**
 * Catalog of merchant-facing recommendations for the Dispute Outcome View.
 *
 * Each entry has:
 *   - title + body: copy that renders verbatim
 *   - urgency: drives title color (critical=red, tip=orange, neutral=black)
 *   - when: predicates the runtime helper checks against the dispute
 *   - link (optional): inline action link
 *
 * Seed entries are pulled verbatim from the Dispute Outcome View mockups
 * (RSM Sprint Reference, §4.4). Every `body` is marked `TODO(riskops)` so
 * the final RiskOps-drafted copy can replace it directly without changing
 * the surrounding wiring.
 *
 * Catalog is exported as `Recommendation[]` rather than a per-cell record
 * so a single entry can apply to multiple (reason × productType) cells via
 * its `when.reasonIn` / `when.productTypeIn` arrays.
 */

// eslint-disable-next-line @typescript-eslint/naming-convention -- This is a constant object.
export const RECOMMENDATIONS_CATALOG: Recommendation[] = [
	// ===================== KEEP DOING (won) =====================
	{
		id: 'shipping-evidence-strong',
		urgency: 'neutral',
		title: 'Strong shipping evidence',
		// TODO(riskops): final copy from RSM-1170
		body: 'Your tracking number and carrier info helped demonstrate delivery. Keep including this for physical goods disputes.',
		when: {
			outcome: 'keep_doing',
			reasonIn: [
				'product_not_received',
				'product_unacceptable',
				'fraudulent',
			],
			productTypeIn: [ 'physical_product', 'multiple' ],
			requireProvided: [ 'shipping_tracking_number', 'shipping_carrier' ],
		},
	},
	{
		id: 'refund-policy-tip',
		urgency: 'tip',
		title: 'Tip: Upload your refund policy next time',
		// TODO(riskops): final copy from RSM-1170
		body: 'You won without it this time, but a published refund policy strengthens fraud disputes.',
		when: {
			outcome: 'keep_doing',
			reasonIn: [ 'fraudulent', 'product_unacceptable' ],
			requireExpectedMissing: [ 'refund_policy' ],
		},
		link: {
			label: 'Set it up now',
			// TODO(riskops): confirm destination with the WC docs team
			href: '/wp-admin/admin.php?page=wc-settings&tab=advanced',
		},
	},

	// ===================== COULD HELP (lost) =====================
	{
		id: 'shipping-tracking-add',
		urgency: 'critical',
		title: 'Add shipping tracking for every order',
		// TODO(riskops): final copy from RSM-1169
		body: '"Product not received" disputes are rarely won without a tracking number showing delivery.',
		when: {
			outcome: 'could_help',
			reasonIn: [ 'product_not_received' ],
			productTypeIn: [ 'physical_product', 'multiple' ],
			requireExpectedMissing: [ 'shipping_tracking_number' ],
		},
		link: {
			label: 'Configure shipping tracking',
			// TODO(riskops): confirm destination with the WC docs team
			href: '/wp-admin/admin.php?page=wc-settings&tab=shipping',
		},
	},
	{
		id: 'proof-of-delivery-collect',
		urgency: 'critical',
		title: 'Collect proof of delivery',
		// TODO(riskops): final copy from RSM-1169
		body: 'Delivery confirmation from the carrier (signature, photo, GPS) is typically the most compelling evidence for this reason code.',
		when: {
			outcome: 'could_help',
			reasonIn: [ 'product_not_received', 'fraudulent' ],
			productTypeIn: [ 'physical_product', 'multiple' ],
			requireExpectedMissing: [
				'shipping_documentation',
				'customer_signature',
			],
		},
	},
	{
		id: 'signature-on-delivery-consider',
		urgency: 'neutral',
		title: 'Consider requiring signature on delivery',
		// TODO(riskops): final copy from RSM-1169
		body: 'For orders over a certain amount, requiring a signature can create a stronger paper trail for demonstrating receipt.',
		when: {
			outcome: 'could_help',
			reasonIn: [ 'product_not_received' ],
			productTypeIn: [ 'physical_product' ],
		},
	},
];
