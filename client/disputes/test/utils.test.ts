/**
 * Internal dependencies
 */
import { isDueWithin } from '../utils';

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
