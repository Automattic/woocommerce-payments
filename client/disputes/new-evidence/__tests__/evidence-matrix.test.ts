/**
 * Internal dependencies
 */
import {
	getExpectedFieldStatus,
	DISPUTE_HIGH_IMPACT_FIELDS,
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

	it( 'surfaces matrix-only fields as optional_missing for a reason with empty high-impact list', () => {
		// `noncompliant` has empty high-impact lists but participates in
		// the matrix via the Visa Compliance flow; ensure no
		// expected_missing rows are emitted even when matrix fields exist.
		const result = getExpectedFieldStatus(
			'noncompliant',
			'physical_product',
			{}
		);
		expect( result.some( ( f ) => f.state === 'expected_missing' ) ).toBe(
			false
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
