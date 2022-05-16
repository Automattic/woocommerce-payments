/**
 * External dependencies
 */
import path from 'path';
import fs from 'fs';

/**
 * Internal dependencies
 */
import {
	composeFXString,
	composeFeeString,
	feeBreakdown,
	composeNetString,
} from '../map-events';

describe( 'Strings in captured events', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		global.wcpaySettings = {
			zeroDecimalCurrencies: [ 'vnd' ],
			connect: {
				country: 'US',
			},
			currencyData: {
				US: {
					code: 'USD',
					symbol: '$',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
			},
		};
	} );

	const loadFixtures = () => {
		const dir = path.join(
			__dirname,
			'../../../../',
			'tests/fixtures/captured-payments'
		);
		return fs
			.readdirSync( dir )
			.filter( ( name ) => '.json' === path.extname( name ) )
			.map( ( name ) => require( path.join( dir, name ) ) );
	};

	describe.each( loadFixtures() )( 'for', ( entry ) => {
		test( entry.title ?? '--undefined title--', () => {
			expect( composeFXString( entry.capturedEvent ) ).toEqual(
				entry.expectation.fxString
			);
			expect( composeFeeString( entry.capturedEvent ) ).toEqual(
				entry.expectation.feeString
			);
			expect( feeBreakdown( entry.capturedEvent ) ).toEqual(
				entry.expectation.feeBreakdown
			);
			expect( composeNetString( entry.capturedEvent ) ).toEqual(
				entry.expectation.netString
			);
		} );
	} );
} );
