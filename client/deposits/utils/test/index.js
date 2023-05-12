/**
 * External dependencies
 */
import momentLib from 'moment';

/**
 * Internal dependencies
 */
import { getDepositDate, getDepositMonthlyAnchorLabel } from './..';

describe( 'Deposits Overview Utils / getDepositDate', () => {
	test( 'returns a display value without a deposit', () => {
		expect( getDepositDate() ).toEqual( '—' );
	} );

	test( 'Returns a well-formated date', () => {
		const deposit = {
			date: new Date( '2019-04-18' ).getTime(),
		};

		expect( getDepositDate( deposit ) ).toEqual( 'April 18, 2019' );
	} );
} );

describe( 'Deposits Overview Utils / getDepositMonthlyAnchorLabel', () => {
	const expectedLabels = [
		{ label: '1st', value: 1 },
		{ label: '2nd', value: 2 },
		{ label: '3rd', value: 3 },
		{ label: '4th', value: 4 },
		{ label: '5th', value: 5 },
		{ label: '6th', value: 6 },
		{ label: '7th', value: 7 },
		{ label: '8th', value: 8 },
		{ label: '9th', value: 9 },
		{ label: '10th', value: 10 },
		{ label: '11th', value: 11 },
		{ label: '12th', value: 12 },
		{ label: '13th', value: 13 },
		{ label: '14th', value: 14 },
		{ label: '15th', value: 15 },
		{ label: '16th', value: 16 },
		{ label: '17th', value: 17 },
		{ label: '18th', value: 18 },
		{ label: '19th', value: 19 },
		{ label: '20th', value: 20 },
		{ label: '21st', value: 21 },
		{ label: '22nd', value: 22 },
		{ label: '23rd', value: 23 },
		{ label: '24th', value: 24 },
		{ label: '25th', value: 25 },
		{ label: '26th', value: 26 },
		{ label: '27th', value: 27 },
		{ label: '28th', value: 28 },
		{
			label: 'Last day of the month',
			value: 31,
		},
	];

	test( 'returns the expected label', () => {
		momentLib.locale( 'en' );
		expectedLabels.forEach( ( expectedLabel ) => {
			expect(
				getDepositMonthlyAnchorLabel( {
					monthlyAnchor: expectedLabel.value,
				} )
			).toEqual( expectedLabel.label );
		} );
	} );

	test( 'returns a lowercase value with false capitalize argument', () => {
		momentLib.locale( 'en' );
		expect(
			getDepositMonthlyAnchorLabel( {
				monthlyAnchor: 31,
				capitalize: false,
			} )
		).toEqual( 'last day of the month' );
	} );
} );
