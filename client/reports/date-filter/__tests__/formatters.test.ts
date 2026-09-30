/** @format */

jest.mock( 'wcpay/utils/date-time', () => ( {
	formatDateTimeFromString: ( s: string ) => {
		// `s` is YYYY-MM-DD; mimic dd/mm/yyyy presentation.
		const [ y, m, d ] = s.split( '-' );
		return `${ d }/${ m }/${ y }`;
	},
} ) );

import {
	formatDateFilterChipLabel,
	formatDateFilterSummary,
	operatorLabel,
} from '../formatters';

describe( 'operatorLabel', () => {
	it( 'returns translated operator labels', () => {
		expect( operatorLabel( 'on' ) ).toBe( 'On' );
		expect( operatorLabel( 'before' ) ).toBe( 'Before' );
		expect( operatorLabel( 'after' ) ).toBe( 'After' );
		expect( operatorLabel( 'between' ) ).toBe( 'Between' );
	} );
} );

describe( 'formatDateFilterSummary', () => {
	it( 'formats single-date operators', () => {
		expect(
			formatDateFilterSummary( { operator: 'on', value: '2026-05-18' } )
		).toBe( '18/05/2026' );
		expect(
			formatDateFilterSummary( {
				operator: 'before',
				value: '2026-05-18',
			} )
		).toBe( '18/05/2026' );
	} );

	it( 'formats between as "<start> and <end>"', () => {
		expect(
			formatDateFilterSummary( {
				operator: 'between',
				value: [ '2026-05-01', '2026-05-18' ],
			} )
		).toBe( '01/05/2026 and 18/05/2026' );
	} );
} );

describe( 'formatDateFilterChipLabel', () => {
	it( 'combines operator and summary', () => {
		expect(
			formatDateFilterChipLabel( {
				operator: 'between',
				value: [ '2026-05-01', '2026-05-18' ],
			} )
		).toBe( 'Between: 01/05/2026 and 18/05/2026' );
	} );
} );
