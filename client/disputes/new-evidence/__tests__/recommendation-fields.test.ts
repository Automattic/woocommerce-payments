/**
 * Internal dependencies
 */
import { getRecommendationFields } from '../recommendation-fields';

describe( 'getRecommendationFields', () => {
	describe( 'could_help branch', () => {
		it( 'returns only expected_missing items from the helper output', () => {
			const result = getRecommendationFields(
				'product_not_received',
				'physical_product',
				{},
				'could_help'
			);

			expect( result.length ).toBeGreaterThan( 0 );
			expect(
				result.every( ( field ) => field.state === 'expected_missing' )
			).toBe( true );
		} );

		it( 'returns an empty array when every high-impact field is provided', () => {
			const result = getRecommendationFields(
				'product_not_received',
				'physical_product',
				{
					shipping_address: '123 Main St',
					shipping_tracking_number: '1Z999',
					shipping_documentation: 'tracked',
					shipping_carrier: 'UPS',
					shipping_date: '2026-04-15',
				},
				'could_help'
			);

			expect( result ).toEqual( [] );
		} );
	} );

	describe( 'keep_doing branch', () => {
		it( 'returns provided high-impact fields for the (reason × productType) cell', () => {
			const result = getRecommendationFields(
				'product_not_received',
				'physical_product',
				{
					shipping_address: '123 Main St',
					shipping_tracking_number: '1Z999',
				},
				'keep_doing'
			);

			expect( result.map( ( field ) => field.key ).sort() ).toEqual( [
				'shipping_address',
				'shipping_tracking_number',
			] );
			expect(
				result.every( ( field ) => field.state === 'provided' )
			).toBe( true );
		} );

		it( 'excludes provided topical-only fields (regression guard)', () => {
			// `refund_policy` is `DISPUTE_TOPICAL_FIELDS` for fraudulent +
			// physical_product, not `DISPUTE_HIGH_IMPACT_FIELDS`. Providing
			// it should not surface in "what to keep doing".
			const result = getRecommendationFields(
				'fraudulent',
				'physical_product',
				{
					shipping_date: '2026-04-15',
					refund_policy: 'No returns after 30 days.',
				},
				'keep_doing'
			);

			expect( result.map( ( field ) => field.key ) ).toEqual( [
				'shipping_date',
			] );
		} );

		it( 'narrows on productType: provided high-impact for one cell is excluded for another', () => {
			// `shipping_date` is high-impact for fraudulent + physical_product
			// but not for fraudulent + digital_product_or_service (which uses
			// `service_date` instead).
			const evidence = {
				shipping_date: '2026-04-15',
				service_date: '2026-04-15',
				customer_communication: 'email thread',
			};

			const physical = getRecommendationFields(
				'fraudulent',
				'physical_product',
				evidence,
				'keep_doing'
			);
			const digital = getRecommendationFields(
				'fraudulent',
				'digital_product_or_service',
				evidence,
				'keep_doing'
			);

			expect( physical.map( ( field ) => field.key ).sort() ).toEqual( [
				'customer_communication',
				'shipping_date',
			] );
			expect( digital.map( ( field ) => field.key ).sort() ).toEqual( [
				'customer_communication',
				'service_date',
			] );
		} );

		it( 'returns an empty array when no high-impact fields are provided', () => {
			const result = getRecommendationFields(
				'product_not_received',
				'physical_product',
				{},
				'keep_doing'
			);

			expect( result ).toEqual( [] );
		} );

		it( 'returns an empty array for a reason × productType cell with no high-impact entries', () => {
			// `bank_cannot_process` is `emptyByProductType()` across the
			// board; nothing should surface even when evidence is provided.
			const result = getRecommendationFields(
				'bank_cannot_process',
				'physical_product',
				{ refund_policy: 'No returns.' },
				'keep_doing'
			);

			expect( result ).toEqual( [] );
		} );
	} );

	it( 'returns an empty array for an unknown reason in both branches', () => {
		const couldHelp = getRecommendationFields(
			'totally_unknown_reason',
			'physical_product',
			{ shipping_address: '123 Main St' },
			'could_help'
		);
		const keepDoing = getRecommendationFields(
			'totally_unknown_reason',
			'physical_product',
			{ shipping_address: '123 Main St' },
			'keep_doing'
		);

		expect( couldHelp ).toEqual( [] );
		expect( keepDoing ).toEqual( [] );
	} );
} );
