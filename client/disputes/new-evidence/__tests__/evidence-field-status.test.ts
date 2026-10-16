/**
 * Internal dependencies
 */
import { getExpectedFieldStatus } from '../evidence-field-status';
import { DISPUTE_HIGH_IMPACT_FIELDS } from '../constants/high-impact-fields';
import { DISPUTE_TOPICAL_FIELDS } from '../constants/topical-fields';

describe( 'getExpectedFieldStatus', () => {
	it( 'marks a high-impact field populated in evidence as provided', () => {
		const result = getExpectedFieldStatus(
			'product_not_received',
			'physical_product',
			{ shipping_address: '123 Main St' }
		);
		const shippingAddress = result.find(
			( f ) => f.key === 'shipping_address'
		);
		expect( shippingAddress ).toBeDefined();
		expect( shippingAddress?.state ).toBe( 'provided' );
	} );

	it( 'marks a high-impact field missing from evidence as expected_missing', () => {
		const result = getExpectedFieldStatus(
			'product_not_received',
			'physical_product',
			{}
		);
		const shippingAddress = result.find(
			( f ) => f.key === 'shipping_address'
		);
		expect( shippingAddress ).toBeDefined();
		expect( shippingAddress?.state ).toBe( 'expected_missing' );
	} );

	it( 'marks a matrix-only field missing from evidence as optional_missing', () => {
		const result = getExpectedFieldStatus(
			'fraudulent',
			'physical_product',
			{}
		);
		const refundPolicy = result.find( ( f ) => f.key === 'refund_policy' );
		expect( refundPolicy ).toBeDefined();
		expect( refundPolicy?.state ).toBe( 'optional_missing' );
	} );

	it( 'tracks shipping_date (not service_date) as the fulfilment date for fraudulent + physical_product', () => {
		// Regression: wizard collects `shipping_date`, not `service_date`, for physical_product.
		const result = getExpectedFieldStatus(
			'fraudulent',
			'physical_product',
			{
				shipping_date: '2026-04-15',
			}
		);
		expect( result.some( ( f ) => f.key === 'service_date' ) ).toBe(
			false
		);
		const shippingDate = result.find( ( f ) => f.key === 'shipping_date' );
		expect( shippingDate?.state ).toBe( 'provided' );
	} );

	it.each( [ '', '   ' ] )( 'treats %j as not provided', ( value ) => {
		const result = getExpectedFieldStatus(
			'product_not_received',
			'physical_product',
			{ shipping_address: value }
		);
		const shippingAddress = result.find(
			( f ) => f.key === 'shipping_address'
		);
		expect( shippingAddress?.state ).toBe( 'expected_missing' );
	} );

	it( 'treats an object with all-empty leaves as not provided', () => {
		const result = getExpectedFieldStatus(
			'product_not_received',
			'physical_product',
			{ shipping_address: { line1: '', line2: '', city: '   ' } }
		);
		const shippingAddress = result.find(
			( f ) => f.key === 'shipping_address'
		);
		expect( shippingAddress?.state ).toBe( 'expected_missing' );
	} );

	it( 'treats an object with at least one non-empty leaf as provided', () => {
		const result = getExpectedFieldStatus(
			'product_not_received',
			'physical_product',
			{ shipping_address: { line1: '123 Main St', city: '' } }
		);
		const shippingAddress = result.find(
			( f ) => f.key === 'shipping_address'
		);
		expect( shippingAddress?.state ).toBe( 'provided' );
	} );

	it( 'returns just the cover letter row for a reason with no high-impact list and no matrix entry', () => {
		const result = getExpectedFieldStatus(
			'bank_cannot_process',
			'physical_product',
			{}
		);
		// `bank_cannot_process × physical_product` has empty entries in
		// every map, so the only row left is the universally-surfaced
		// cover letter (expected_missing when `uncategorized_text` is
		// empty).
		expect( result ).toEqual( [
			{
				key: 'uncategorized_text',
				label: 'Cover letter',
				state: 'expected_missing',
			},
		] );
	} );

	it( 'surfaces matrix-only fields as optional_missing for a cell with empty high-impact list', () => {
		const result = getExpectedFieldStatus(
			'product_unacceptable',
			'event',
			{}
		);
		// Excluding the universally-surfaced cover letter, the only
		// expected_missing-state rows would come from the (empty)
		// high-impact list; the rest are optional_missing from the
		// matrix.
		const nonCoverLetter = result.filter(
			( f ) => f.key !== 'uncategorized_text'
		);
		expect(
			nonCoverLetter.some( ( f ) => f.state === 'expected_missing' )
		).toBe( false );
		expect(
			nonCoverLetter.some( ( f ) => f.state === 'optional_missing' )
		).toBe( true );
	} );

	it( 'returns just the cover letter row for an unrecognised reason string', () => {
		const result = getExpectedFieldStatus(
			'not_a_real_reason',
			'physical_product',
			{}
		);
		expect( result ).toEqual( [
			{
				key: 'uncategorized_text',
				label: 'Cover letter',
				state: 'expected_missing',
			},
		] );
	} );

	it( 'returns just the cover letter row for an unrecognised product type string', () => {
		const result = getExpectedFieldStatus(
			'product_not_received',
			'not_a_real_product_type',
			{}
		);
		expect( result ).toEqual( [
			{
				key: 'uncategorized_text',
				label: 'Cover letter',
				state: 'expected_missing',
			},
		] );
	} );

	it( 'narrows expected_missing rows to the product type cell', () => {
		const physical = getExpectedFieldStatus(
			'product_not_received',
			'physical_product',
			{}
		);
		const digital = getExpectedFieldStatus(
			'product_not_received',
			'digital_product_or_service',
			{}
		);
		expect(
			physical.some(
				( f ) =>
					f.key === 'shipping_address' &&
					f.state === 'expected_missing'
			)
		).toBe( true );
		expect( digital.some( ( f ) => f.key === 'shipping_address' ) ).toBe(
			false
		);
	} );

	it( 'resolves the product-type-specific label for cancellation_policy', () => {
		const result = getExpectedFieldStatus(
			'subscription_canceled',
			'physical_product',
			{}
		);
		const cancellationPolicy = result.find(
			( f ) => f.key === 'cancellation_policy'
		);
		expect( cancellationPolicy?.label ).toBe( 'Terms of service' );
	} );

	it( 'falls back to FALLBACK_EVIDENCE_FIELD_LABELS for text-only Stripe keys', () => {
		const result = getExpectedFieldStatus(
			'product_not_received',
			'physical_product',
			{}
		);
		const shippingTracking = result.find(
			( f ) => f.key === 'shipping_tracking_number'
		);
		expect( shippingTracking?.label ).toBe( 'Shipping tracking number' );
	} );

	it( 'falls back to FALLBACK_EVIDENCE_FIELD_LABELS for base fields not in the wizard cell', () => {
		const result = getExpectedFieldStatus(
			'product_not_received',
			'digital_product_or_service',
			{}
		);
		expect(
			result.find( ( f ) => f.key === 'customer_communication' )?.label
		).toBe( 'Customer communication' );
		expect( result.find( ( f ) => f.key === 'receipt' )?.label ).toBe(
			'Order receipt'
		);
	} );

	it( 'falls back to FALLBACK_EVIDENCE_FIELD_LABELS for shipping_documentation across reasons', () => {
		const result = getExpectedFieldStatus(
			'product_not_received',
			'physical_product',
			{}
		);
		expect(
			result.find( ( f ) => f.key === 'shipping_documentation' )?.label
		).toBe( 'Shipping documentation' );
	} );

	it( 'never renders a raw snake_case key as the label for any high-impact cell', () => {
		// Defense-in-depth: every high-impact key across every cell must
		// resolve to a human-readable label, either via the wizard matrix
		// or via FALLBACK_EVIDENCE_FIELD_LABELS. If this test fails, add
		// the missing key to FALLBACK_EVIDENCE_FIELD_LABELS.
		//
		// `row` is asserted defined explicitly so a future change that
		// stops emitting a high-impact key surfaces as a test failure
		// rather than a silent pass via optional-chaining on undefined.
		Object.entries( DISPUTE_HIGH_IMPACT_FIELDS ).forEach(
			( [ reason, byProductType ] ) => {
				Object.entries( byProductType ).forEach(
					( [ productType, keys ] ) => {
						const result = getExpectedFieldStatus(
							reason,
							productType,
							{}
						);
						keys.forEach( ( key ) => {
							const row = result.find( ( f ) => f.key === key );
							expect( row ).toBeDefined();
							expect( row?.label ).not.toBe( key );
						} );
					}
				);
			}
		);
	} );

	it( 'falls back to FALLBACK_EVIDENCE_FIELD_LABELS for cells whose matrix omits the high-impact key', () => {
		const result = getExpectedFieldStatus(
			'subscription_canceled',
			'other',
			{}
		);
		const cancellationRebuttal = result.find(
			( f ) => f.key === 'cancellation_rebuttal'
		);
		expect( cancellationRebuttal?.label ).toBe( 'Cancellation logs' );
	} );

	it( 'surfaces topical fields as optional_missing when absent from the wizard matrix cell', () => {
		const result = getExpectedFieldStatus(
			'subscription_canceled',
			'digital_product_or_service',
			{}
		);
		const refundPolicy = result.find( ( f ) => f.key === 'refund_policy' );
		expect( refundPolicy ).toBeDefined();
		expect( refundPolicy?.state ).toBe( 'optional_missing' );
		expect( refundPolicy?.label ).toBe( 'Refund policy' );
	} );

	it( 'marks a topical field populated in evidence as provided', () => {
		const result = getExpectedFieldStatus(
			'product_unacceptable',
			'other',
			{ refund_policy: 'file_abc123' }
		);
		const refundPolicy = result.find( ( f ) => f.key === 'refund_policy' );
		expect( refundPolicy?.state ).toBe( 'provided' );
	} );

	it( 'does not double-emit when a topical field is also present in the wizard matrix cell', () => {
		const result = getExpectedFieldStatus(
			'subscription_canceled',
			'physical_product',
			{}
		);
		const refundPolicyRows = result.filter(
			( f ) => f.key === 'refund_policy'
		);
		expect( refundPolicyRows ).toHaveLength( 1 );
	} );

	it( 'mirrors the wizard customer_communication base-field merge for cells that omit it explicitly', () => {
		const result = getExpectedFieldStatus(
			'duplicate',
			'physical_product',
			{}
		);
		const customerCommunication = result.find(
			( f ) => f.key === 'customer_communication'
		);
		expect( customerCommunication ).toBeDefined();
		expect( customerCommunication?.state ).toBe( 'optional_missing' );
		expect( customerCommunication?.label ).toBe( 'Customer communication' );
	} );

	it( 'does not synthesise customer_communication when no wizard cell matches', () => {
		const result = getExpectedFieldStatus(
			'unrecognized',
			'physical_product',
			{}
		);
		// Only the universal cover letter row survives; the base-field
		// merge for customer_communication is gated on a matching cell.
		expect(
			result.find( ( f ) => f.key === 'customer_communication' )
		).toBeUndefined();
	} );
} );

describe( 'DISPUTE_HIGH_IMPACT_FIELDS', () => {
	it( 'excludes auto-populated, hybrid, and catch-all fields across every cell', () => {
		Object.values( DISPUTE_HIGH_IMPACT_FIELDS ).forEach(
			( byProductType ) => {
				Object.values( byProductType ).forEach( ( fields ) => {
					expect( fields ).not.toContain( 'customer_purchase_ip' );
					expect( fields ).not.toContain( 'customer_name' );
					expect( fields ).not.toContain( 'customer_email_address' );
					expect( fields ).not.toContain( 'billing_address' );
					expect( fields ).not.toContain( 'product_description' );
					expect( fields ).not.toContain( 'uncategorized_file' );
					expect( fields ).not.toContain( 'uncategorized_text' );
				} );
			}
		);
	} );
} );

describe( 'composite-key label collision handling', () => {
	it( 'falls back to FALLBACK label when composite cells disagree on duplicate_charge_documentation', () => {
		const result = getExpectedFieldStatus(
			'duplicate',
			'physical_product',
			{}
		);
		const row = result.find(
			( f ) => f.key === 'duplicate_charge_documentation'
		);
		expect( row?.label ).toBe( 'Duplicate charge documentation' );
	} );

	it( 'falls back to FALLBACK label when composite cells disagree on uncategorized_file', () => {
		const result = getExpectedFieldStatus(
			'credit_not_processed',
			'physical_product',
			{}
		);
		const row = result.find( ( f ) => f.key === 'uncategorized_file' );
		expect( row?.label ).toBe( 'Other documents' );
		// Negative assertion guards against silent regression in collision
		// detection: "Other documents" coincides with one of the colliding
		// matrix labels, so a broken collision detector returning the first
		// match could pass the positive assertion alone. "Proof of acceptance"
		// appears only in the other status branch and never in the fallback
		// table, so it must never be the resolved label.
		expect( row?.label ).not.toBe( 'Proof of acceptance' );
	} );

	it( 'still resolves the productType-specific label when composite cells agree', () => {
		const result = getExpectedFieldStatus(
			'credit_not_processed',
			'physical_product',
			{}
		);
		const row = result.find( ( f ) => f.key === 'customer_communication' );
		expect( row?.label ).toBe( 'Customer communication' );
	} );
} );

describe( 'cover letter row', () => {
	it( 'renders as provided when uncategorized_text has content', () => {
		const result = getExpectedFieldStatus(
			'fraudulent',
			'physical_product',
			{ uncategorized_text: 'Dear bank, ...' }
		);
		const row = result.find( ( f ) => f.key === 'uncategorized_text' );
		expect( row ).toBeDefined();
		expect( row?.state ).toBe( 'provided' );
		expect( row?.label ).toBe( 'Cover letter' );
	} );

	it( 'renders as expected_missing when uncategorized_text is empty', () => {
		const result = getExpectedFieldStatus(
			'fraudulent',
			'physical_product',
			{ uncategorized_text: '' }
		);
		const row = result.find( ( f ) => f.key === 'uncategorized_text' );
		expect( row?.state ).toBe( 'expected_missing' );
	} );

	it( 'renders as expected_missing when uncategorized_text is absent', () => {
		const result = getExpectedFieldStatus(
			'fraudulent',
			'physical_product',
			{}
		);
		const row = result.find( ( f ) => f.key === 'uncategorized_text' );
		expect( row?.state ).toBe( 'expected_missing' );
	} );

	it( 'surfaces the cover letter row for reasons with no high-impact entries', () => {
		// `bank_cannot_process` is an empty cell in every map; the cover
		// letter row is still emitted.
		const result = getExpectedFieldStatus(
			'bank_cannot_process',
			'physical_product',
			{}
		);
		expect(
			result.find( ( f ) => f.key === 'uncategorized_text' )
		).toBeDefined();
	} );

	it( 'positions the cover letter row before the optional_missing block', () => {
		// On a cell mixing expected_missing + optional_missing rows, the
		// cover letter should sit ahead of the optional ones so the
		// inline / disclosure split in the UI groups it with the other
		// "expected" entries.
		const result = getExpectedFieldStatus(
			'fraudulent',
			'physical_product',
			{}
		);
		const coverLetterIdx = result.findIndex(
			( f ) => f.key === 'uncategorized_text'
		);
		const firstOptionalIdx = result.findIndex(
			( f ) => f.state === 'optional_missing'
		);
		expect( coverLetterIdx ).toBeGreaterThanOrEqual( 0 );
		expect( firstOptionalIdx ).toBeGreaterThan( coverLetterIdx );
	} );
} );

describe( 'DISPUTE_TOPICAL_FIELDS', () => {
	it( 'excludes auto-populated, hybrid, and catch-all fields across every cell', () => {
		Object.values( DISPUTE_TOPICAL_FIELDS ).forEach( ( byProductType ) => {
			Object.values( byProductType ).forEach( ( fields ) => {
				expect( fields ).not.toContain( 'customer_purchase_ip' );
				expect( fields ).not.toContain( 'customer_name' );
				expect( fields ).not.toContain( 'customer_email_address' );
				expect( fields ).not.toContain( 'billing_address' );
				expect( fields ).not.toContain( 'product_description' );
				expect( fields ).not.toContain( 'uncategorized_file' );
				expect( fields ).not.toContain( 'uncategorized_text' );
			} );
		} );
	} );
} );
