/**
 * Internal dependencies
 */
import { isDueWithin, isInquiry, isVisaComplianceDispute } from '../utils';
import type { Dispute } from 'wcpay/types/disputes';

describe( 'isDueWithin', () => {
	// 2021-01-01T00:00:00.000Z
	const mockUnixTime = 1609459200;
	const hourInSeconds = 60 * 60;
	const now = Date.now();

	beforeAll( () => {
		// Set current date to 2021-01-01.
		Date.now = () => mockUnixTime * 1000;
	} );

	afterAll( () => {
		// Reset current date.
		Date.now = () => now;
	} );

	test( 'returns false if dueBy is not a valid date', () => {
		expect( isDueWithin( { dueBy: 'not a date', days: 1 } ) ).toBe( false );
	} );

	test( 'returns false if dueBy is not within the specified number of days', () => {
		// Over 1 day
		expect(
			isDueWithin( {
				dueBy: mockUnixTime + hourInSeconds * 24.01,
				days: 1,
			} )
		).toBe( false );
		expect(
			isDueWithin( { dueBy: '2021-01-02T00:00:01.000Z', days: 1 } )
		).toBe( false );

		// Over 7 days
		expect(
			isDueWithin( {
				dueBy: mockUnixTime + hourInSeconds * 168.05,
				days: 7,
			} )
		).toBe( false );
		expect(
			isDueWithin( { dueBy: '2021-01-08T00:00:01.000Z', days: 7 } )
		).toBe( false );
	} );

	test( 'returns true if dueBy is within or equal to the specified number of days', () => {
		// Within 1 day
		expect(
			isDueWithin( {
				dueBy: mockUnixTime + hourInSeconds * 23.95,
				days: 1,
			} )
		).toBe( true );
		expect(
			isDueWithin( { dueBy: '2021-01-01T23:59:00.000Z', days: 1 } )
		).toBe( true );

		// Exactly 1 day
		expect(
			isDueWithin( {
				dueBy: mockUnixTime + hourInSeconds * 24,
				days: 1,
			} )
		).toBe( true );
		expect(
			isDueWithin( { dueBy: '2021-01-02T00:00:00.000Z', days: 1 } )
		).toBe( true );

		// Exactly 7 days
		expect(
			isDueWithin( {
				dueBy: mockUnixTime + hourInSeconds * 168,
				days: 7,
			} )
		).toBe( true );
		expect(
			isDueWithin( { dueBy: '2021-01-08T00:00:00.000Z', days: 7 } )
		).toBe( true );
	} );

	test( 'returns false if dueBy is in the past', () => {
		expect(
			isDueWithin( {
				dueBy: mockUnixTime - hourInSeconds * 1,
				days: 10,
			} )
		).toBe( false );
		expect(
			isDueWithin( { dueBy: '2020-12-31T23:59:00.000Z', days: 10 } )
		).toBe( false );
	} );
} );

describe( 'isInquiry', () => {
	test( 'returns true if status is inquiry', () => {
		expect( isInquiry( 'warning_needs_response' ) ).toBe( true );
		expect( isInquiry( 'warning_under_review' ) ).toBe( true );
		expect( isInquiry( 'warning_closed' ) ).toBe( true );
	} );

	test( 'returns false if status is not inquiry', () => {
		expect( isInquiry( 'needs_response' ) ).toBe( false );
		expect( isInquiry( 'under_review' ) ).toBe( false );
		expect( isInquiry( 'charge_refunded' ) ).toBe( false );
		expect( isInquiry( 'won' ) ).toBe( false );
		expect( isInquiry( 'lost' ) ).toBe( false );
	} );
} );

describe( 'isVisaComplianceDispute', () => {
	test( 'returns true if dispute reason is noncompliant', () => {
		const dispute = {
			reason: 'noncompliant',
			enhanced_eligibility_types: [],
		} as Pick< Dispute, 'reason' | 'enhanced_eligibility_types' >;
		expect( isVisaComplianceDispute( dispute ) ).toBe( true );
	} );

	test( 'returns true if enhanced_eligibility_types includes visa_compliance', () => {
		const dispute = {
			reason: 'fraudulent',
			enhanced_eligibility_types: [ 'visa_compliance' ],
		} as Pick< Dispute, 'reason' | 'enhanced_eligibility_types' >;
		expect( isVisaComplianceDispute( dispute ) ).toBe( true );
	} );

	test( 'returns true if both reason is noncompliant and enhanced_eligibility_types includes visa_compliance', () => {
		const dispute = {
			reason: 'noncompliant',
			enhanced_eligibility_types: [ 'visa_compliance' ],
		} as Pick< Dispute, 'reason' | 'enhanced_eligibility_types' >;
		expect( isVisaComplianceDispute( dispute ) ).toBe( true );
	} );

	test( 'returns true if visa_compliance is in a list of enhanced_eligibility_types', () => {
		const dispute = {
			reason: 'duplicate',
			enhanced_eligibility_types: [
				'early_fraud_warning',
				'visa_compliance',
				'other_type',
			],
		} as Pick< Dispute, 'reason' | 'enhanced_eligibility_types' >;
		expect( isVisaComplianceDispute( dispute ) ).toBe( true );
	} );

	test( 'returns false if dispute reason is not noncompliant and no visa_compliance in enhanced_eligibility_types', () => {
		const dispute = {
			reason: 'fraudulent',
			enhanced_eligibility_types: [],
		} as Pick< Dispute, 'reason' | 'enhanced_eligibility_types' >;
		expect( isVisaComplianceDispute( dispute ) ).toBe( false );
	} );

	test( 'returns false if enhanced_eligibility_types is undefined', () => {
		const dispute = {
			reason: 'product_not_received',
			enhanced_eligibility_types: undefined,
		} as Pick< Dispute, 'reason' | 'enhanced_eligibility_types' >;
		expect( isVisaComplianceDispute( dispute ) ).toBe( false );
	} );

	test( 'returns false if enhanced_eligibility_types does not include visa_compliance', () => {
		const dispute = {
			reason: 'duplicate',
			enhanced_eligibility_types: [ 'early_fraud_warning', 'other_type' ],
		} as Pick< Dispute, 'reason' | 'enhanced_eligibility_types' >;
		expect( isVisaComplianceDispute( dispute ) ).toBe( false );
	} );

	test( 'returns false if dispute is null', () => {
		expect( isVisaComplianceDispute( null as any ) ).toBe( false );
	} );

	test( 'returns false if dispute is undefined', () => {
		expect( isVisaComplianceDispute( undefined as any ) ).toBe( false );
	} );
} );
