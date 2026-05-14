/** @format */

/**
 * Hand-built `EvidenceFieldStatus[]` fixtures for the EvidenceSubmittedList
 * component. Representative of the shape returned by `getExpectedFieldStatus`
 * (see `client/disputes/new-evidence/evidence-field-status.ts`), including
 * the universal cover-letter row (`uncategorized_text`) that the helper
 * always appends. Tests can exercise each tri-state without spinning up a
 * full dispute.
 */

/**
 * Internal dependencies
 */
import type { EvidenceFieldStatus } from 'wcpay/disputes/new-evidence/types';

/**
 * Won case: `fraudulent` × `physical_product` where the merchant submitted strong evidence.
 * Most fields provided (including the auto-generated cover letter); a couple of optional
 * fields left blank (muted dashes).
 */
export const wonFraudulentPhysical: EvidenceFieldStatus[] = [
	{
		key: 'customer_communication',
		label: 'Customer communication',
		state: 'provided',
	},
	{
		key: 'uncategorized_text',
		label: 'Cover letter',
		state: 'provided',
	},
	{
		key: 'shipping_documentation',
		label: 'Shipping documentation',
		state: 'provided',
	},
	{
		key: 'shipping_address',
		label: 'Shipping address',
		state: 'provided',
	},
	{
		key: 'shipping_tracking_number',
		label: 'Shipping tracking number',
		state: 'provided',
	},
	{
		key: 'customer_signature',
		label: 'Customer signature',
		state: 'provided',
	},
	{
		key: 'receipt',
		label: 'Receipt',
		state: 'optional_missing',
	},
	{
		key: 'refund_policy',
		label: 'Refund policy',
		state: 'optional_missing',
	},
];

/**
 * Lost case: `product_unacceptable` × `physical_product` where the merchant submitted
 * partial evidence. Several expected fields are missing (red ✗); some optional fields
 * also left blank. Cover letter is provided here as the typical case (merchant engaged
 * with the wizard, which auto-fills it).
 */
export const lostProductUnacceptablePhysical: EvidenceFieldStatus[] = [
	{
		key: 'customer_communication',
		label: 'Customer communication',
		state: 'provided',
	},
	{
		key: 'uncategorized_text',
		label: 'Cover letter',
		state: 'provided',
	},
	{
		key: 'shipping_documentation',
		label: 'Shipping documentation',
		state: 'expected_missing',
	},
	{
		key: 'shipping_address',
		label: 'Shipping address',
		state: 'expected_missing',
	},
	{
		key: 'shipping_date',
		label: 'Shipping date',
		state: 'expected_missing',
	},
	{
		key: 'refund_policy',
		label: 'Refund policy',
		state: 'optional_missing',
	},
];
