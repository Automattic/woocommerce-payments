/** @format */

/**
 * Mock `EvidenceFieldStatus[]` fixtures for the EvidenceSubmittedList component.
 *
 * These mimic the shape returned by `getExpectedFieldStatus(reason, productType, evidence)`
 * from `client/disputes/new-evidence/helpers.ts`. They are not runtime artifacts of the
 * real helper — they are hand-built so visual reviews and tests can exercise each tri-state
 * (`provided`, `expected_missing`, `optional_missing`) without spinning up a full dispute.
 *
 * Future Storybook setup can import these directly as story args.
 */

/**
 * Internal dependencies
 */
import type { EvidenceFieldStatus } from 'wcpay/disputes/new-evidence/types';

/**
 * Won case: `fraudulent` × `physical_product` where the merchant submitted strong evidence.
 * Most fields provided; a couple of optional fields left blank (muted dashes).
 */
export const wonFraudulentPhysical: EvidenceFieldStatus[] = [
	{
		key: 'customer_communication',
		label: 'Customer communication',
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
 * also left blank.
 */
export const lostProductUnacceptablePhysical: EvidenceFieldStatus[] = [
	{
		key: 'customer_communication',
		label: 'Customer communication',
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
	{
		key: 'uncategorized_text',
		label: 'Additional information',
		state: 'optional_missing',
	},
];
