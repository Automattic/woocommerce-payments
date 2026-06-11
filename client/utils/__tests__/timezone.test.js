/** @format */

const mockFormat = jest.fn( () => '+02:00' );
const mockMoment = jest.fn( () => ( {
	format: mockFormat,
} ) );

jest.mock( 'moment', () => ( {
	__esModule: true,
	default: mockMoment,
} ) );

jest.mock( '@wordpress/date', () => ( {
	dateI18n: jest.fn( ( _format, value ) => value ),
} ) );

describe( 'getUserTimeZone', () => {
	it( 'recomputes the offset on every call so DST transitions are reflected', () => {
		jest.isolateModules( () => {
			const { getUserTimeZone } = require( '..' );

			expect( getUserTimeZone() ).toBe( '+02:00' );
			expect( getUserTimeZone() ).toBe( '+02:00' );
		} );

		expect( mockMoment ).toHaveBeenCalledTimes( 2 );
		expect( mockFormat ).toHaveBeenCalledTimes( 2 );
	} );
} );
