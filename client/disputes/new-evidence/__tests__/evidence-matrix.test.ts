/**
 * Internal dependencies
 */
import {
	getExpectedFieldStatus,
	DISPUTE_HIGH_IMPACT_FIELDS,
} from '../evidence-matrix';

describe( 'getExpectedFieldStatus', () => {
	it( 'marks a high-impact field populated in evidence as provided', () => {
		const result = getExpectedFieldStatus( 'product_not_received', {
			shipping_address: '123 Main St',
		} );
		const shippingAddress = result.find(
			( f ) => f.key === 'shipping_address'
		);
		expect( shippingAddress ).toBeDefined();
		expect( shippingAddress?.state ).toBe( 'provided' );
	} );

	it( 'marks a high-impact field missing from evidence as expected_missing', () => {
		const result = getExpectedFieldStatus( 'product_not_received', {} );
		const shippingAddress = result.find(
			( f ) => f.key === 'shipping_address'
		);
		expect( shippingAddress ).toBeDefined();
		expect( shippingAddress?.state ).toBe( 'expected_missing' );
	} );

	it( 'marks a matrix-only field missing from evidence as optional_missing', () => {
		// `refund_policy` appears in the fraudulent matrix but is not highly
		// recommended for fraudulent, so it must surface as optional_missing
		// when empty.
		const result = getExpectedFieldStatus( 'fraudulent', {} );
		const refundPolicy = result.find( ( f ) => f.key === 'refund_policy' );
		expect( refundPolicy ).toBeDefined();
		expect( refundPolicy?.state ).toBe( 'optional_missing' );
	} );

	it.each( [ '', '   ' ] )( 'treats %j as not provided', ( value ) => {
		const result = getExpectedFieldStatus( 'product_not_received', {
			shipping_address: value,
		} );
		const shippingAddress = result.find(
			( f ) => f.key === 'shipping_address'
		);
		expect( shippingAddress?.state ).toBe( 'expected_missing' );
	} );

	it( 'treats an object with all-empty leaves as not provided', () => {
		const result = getExpectedFieldStatus( 'product_not_received', {
			shipping_address: { line1: '', line2: '', city: '   ' },
		} );
		const shippingAddress = result.find(
			( f ) => f.key === 'shipping_address'
		);
		expect( shippingAddress?.state ).toBe( 'expected_missing' );
	} );

	it( 'treats an object with at least one non-empty leaf as provided', () => {
		const result = getExpectedFieldStatus( 'product_not_received', {
			shipping_address: { line1: '123 Main St', city: '' },
		} );
		const shippingAddress = result.find(
			( f ) => f.key === 'shipping_address'
		);
		expect( shippingAddress?.state ).toBe( 'provided' );
	} );

	it( 'returns an empty array for a reason with no high-impact list and no matrix entry', () => {
		const result = getExpectedFieldStatus( 'bank_cannot_process', {} );
		expect( result ).toEqual( [] );
	} );

	it( 'surfaces matrix-only fields as optional_missing for a reason with empty high-impact list', () => {
		// `noncompliant` has an empty DISPUTE_HIGH_IMPACT_FIELDS entry but
		// participates in the matrix via the Visa Compliance flow; ensure no
		// expected_missing rows are emitted even when matrix fields exist.
		const result = getExpectedFieldStatus( 'noncompliant', {} );
		expect( result.some( ( f ) => f.state === 'expected_missing' ) ).toBe(
			false
		);
	} );

	it( 'returns an empty array for an unrecognised reason string', () => {
		const result = getExpectedFieldStatus( 'not_a_real_reason', {} );
		expect( result ).toEqual( [] );
	} );

	it( 'resolves labels from the evidence matrix when keys overlap', () => {
		const result = getExpectedFieldStatus( 'subscription_canceled', {} );
		const cancellationPolicy = result.find(
			( f ) => f.key === 'cancellation_policy'
		);
		// Pinned to the actual matrix label for cancellation_policy under
		// subscription_canceled. Matrix variants label this as "Terms of
		// service" rather than "Cancellation policy", which confirms the
		// matrix label wins over the fallback map and surfaces the
		// product-type label-repurposing the consuming PR will need to
		// address.
		expect( cancellationPolicy?.label ).toBe( 'Terms of service' );
	} );

	it( 'falls back to FALLBACK_EVIDENCE_FIELD_LABELS for text-only Stripe keys', () => {
		const result = getExpectedFieldStatus( 'product_not_received', {} );
		const shippingTracking = result.find(
			( f ) => f.key === 'shipping_tracking_number'
		);
		expect( shippingTracking?.label ).toBe( 'Shipping tracking number' );
	} );
} );

describe( 'DISPUTE_HIGH_IMPACT_FIELDS', () => {
	it( 'excludes auto-populated and catch-all fields', () => {
		Object.values( DISPUTE_HIGH_IMPACT_FIELDS ).forEach( ( fields ) => {
			expect( fields ).not.toContain( 'customer_purchase_ip' );
			expect( fields ).not.toContain( 'uncategorized_file' );
			expect( fields ).not.toContain( 'uncategorized_text' );
		} );
	} );
} );
