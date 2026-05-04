/**
 * Internal dependencies
 */
import {
	getExpectedFieldStatus,
	DISPUTE_HIGH_IMPACT_FIELDS,
	DISPUTE_TOPICAL_FIELDS,
} from '../evidence-matrix';

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
		// `refund_policy` appears in the fraudulent matrix but is not
		// high-impact for fraudulent, so it must surface as
		// optional_missing when empty.
		const result = getExpectedFieldStatus(
			'fraudulent',
			'physical_product',
			{}
		);
		const refundPolicy = result.find( ( f ) => f.key === 'refund_policy' );
		expect( refundPolicy ).toBeDefined();
		expect( refundPolicy?.state ).toBe( 'optional_missing' );
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

	it( 'returns an empty array for a reason with no high-impact list and no matrix entry', () => {
		const result = getExpectedFieldStatus(
			'bank_cannot_process',
			'physical_product',
			{}
		);
		expect( result ).toEqual( [] );
	} );

	it( 'surfaces matrix-only fields as optional_missing for a cell with empty high-impact list', () => {
		// `product_unacceptable.event` has an empty high-impact list (no
		// data-backed picks) but the wizard matrix cell for that
		// (reason, productType) pair has entries. They must surface as
		// optional_missing, never expected_missing.
		const result = getExpectedFieldStatus(
			'product_unacceptable',
			'event',
			{}
		);
		expect( result.some( ( f ) => f.state === 'expected_missing' ) ).toBe(
			false
		);
		expect( result.some( ( f ) => f.state === 'optional_missing' ) ).toBe(
			true
		);
	} );

	it( 'returns an empty array for an unrecognised reason string', () => {
		const result = getExpectedFieldStatus(
			'not_a_real_reason',
			'physical_product',
			{}
		);
		expect( result ).toEqual( [] );
	} );

	it( 'returns an empty array for an unrecognised product type string', () => {
		const result = getExpectedFieldStatus(
			'product_not_received',
			'not_a_real_product_type',
			{}
		);
		expect( result ).toEqual( [] );
	} );

	it( 'narrows expected_missing rows to the product type cell', () => {
		// `shipping_address` is high-impact for product_not_received on
		// physical_product but not on digital_product_or_service.
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
		// Subscription_canceled labels cancellation_policy as "Terms of
		// service" across product-type cells in the matrix; this confirms
		// the label resolves through the productType-specific cell.
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
		// `customer_communication` and `receipt` are auto-merged into
		// wizard cells at runtime; the outcome-view helper reads the
		// matrix directly and would otherwise render the raw key.
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
		// `shipping_documentation` is high-impact for several non-CNP
		// cells (PNR/duplicate/PU physical) but isn't listed in those
		// wizard matrix cells; the wizard only references it from CNP
		// cells with a context-specific label ("Return tracking").
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
		// `cancellation_rebuttal` is high-impact for subscription_canceled
		// across all product types, but the wizard matrix cell for `other`
		// deliberately omits it (per spec). Label resolution must fall
		// through to the explicit fallback table rather than render the
		// raw key.
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
		// `refund_policy` is a topical recommendation for
		// subscription_canceled.digital_product_or_service per Catherine's
		// at-a-glance, but the wizard matrix cell deliberately omits it.
		// DISPUTE_TOPICAL_FIELDS is the source for this row.
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
		// `refund_policy` is in `DISPUTE_TOPICAL_FIELDS` only for the
		// cells whose wizard matrix omits it; physical_product is not
		// one of those. The row must come from the wizard matrix path
		// (optional_missing) without a duplicate from the topical map.
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
	// The wizard matrix intentionally labels some keys differently across
	// status branches within a composite-key cell (e.g.
	// `duplicate_charge_documentation` is "Refund receipt" under
	// `__is_duplicate` and "Any additional receipts" under
	// `__is_not_duplicate`). The outcome view has no wizard-time status
	// to disambiguate, so `findMatrixLabel` returns undefined when matches
	// disagree, and `resolveFieldLabel` falls through to a neutral
	// FALLBACK label.

	it( 'falls back to FALLBACK label when composite cells disagree on duplicate_charge_documentation', () => {
		const result = getExpectedFieldStatus(
			'duplicate',
			'physical_product',
			{}
		);
		const row = result.find(
			( f ) => f.key === 'duplicate_charge_documentation'
		);
		// "Refund receipt" and "Any additional receipts" both appear in
		// the wizard matrix for this cell across status branches. The
		// neutral fallback label wins.
		expect( row?.label ).toBe( 'Duplicate charge documentation' );
	} );

	it( 'falls back to FALLBACK label when composite cells disagree on uncategorized_file', () => {
		// In credit_not_processed.physical_product, the wizard matrix
		// labels uncategorized_file as "Other documents" in one status
		// branch and "Proof of acceptance" in another.
		const result = getExpectedFieldStatus(
			'credit_not_processed',
			'physical_product',
			{}
		);
		const row = result.find( ( f ) => f.key === 'uncategorized_file' );
		expect( row?.label ).toBe( 'Other documents' );
		// Negative assertion guards against silent regression in
		// collision detection: "Other documents" coincides with one of
		// the colliding matrix labels, so a broken collision detector
		// returning the first match could pass the positive assertion
		// alone. "Proof of acceptance" appears only in the other status
		// branch and never in the fallback table, so it must never be
		// the resolved label.
		expect( row?.label ).not.toBe( 'Proof of acceptance' );
	} );

	it( 'still resolves the productType-specific label when composite cells agree', () => {
		// `customer_communication` is consistently labelled "Customer
		// communication" across CNP composite cells; that single label
		// must still resolve, not fall through.
		const result = getExpectedFieldStatus(
			'credit_not_processed',
			'physical_product',
			{}
		);
		const row = result.find( ( f ) => f.key === 'customer_communication' );
		expect( row?.label ).toBe( 'Customer communication' );
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
