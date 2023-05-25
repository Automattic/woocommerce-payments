/**
 * Internal dependencies
 */
import { getTimeOfDayString, getGreeting } from '../utils';

describe( 'Welcome utils', () => {
	it( 'getTimeOfDayString returns the correct time of day', () => {
		const date = new Date();
		// set the time to 8am
		date.setHours( 8 );
		const morning = getTimeOfDayString( date );
		// set the time to midday
		date.setHours( 12 );
		const afternoon = getTimeOfDayString( date );
		// set the time to 6pm
		date.setHours( 18 );
		const evening = getTimeOfDayString( date );

		expect( morning ).toEqual( 'morning' );
		expect( afternoon ).toEqual( 'afternoon' );
		expect( evening ).toEqual( 'evening' );
	} );

	it( 'getGreeting returns the correct greeting when provided a name', () => {
		const date = new Date();
		// set the time to 8am
		date.setHours( 8 );
		expect( getGreeting( 'Bingo', date ) ).toEqual(
			'Good morning, Bingo 👋'
		);
	} );

	it( 'getGreeting returns the correct greeting when not name provided', () => {
		const date = new Date();
		// set the time to 8am
		date.setHours( 8 );
		expect( getGreeting( undefined, date ) ).toEqual( 'Good morning 👋' );
	} );
} );
