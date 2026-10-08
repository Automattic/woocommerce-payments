import {
	rememberRefreshedNonces,
	getRefreshedNonces,
	__resetRefreshedNoncesForTests,
} from '../refreshed-nonces-cache';

describe( 'refreshed-nonces-cache', () => {
	afterEach( () => {
		__resetRefreshedNoncesForTests();
	} );

	test( 'returns no nonces when nothing has been remembered', () => {
		expect( getRefreshedNonces() ).toEqual( {} );
	} );

	test( 'remembers the nonces when the server refreshed them', () => {
		const headers = new Headers();
		headers.append( 'Nonce', 'store-api' );
		headers.append( 'X-WooPayments-Tokenized-Cart-Nonce', 'tokenized' );
		headers.append(
			'X-WooPayments-Tokenized-Cart-Session-Nonce',
			'session'
		);

		rememberRefreshedNonces( headers );

		expect( getRefreshedNonces() ).toEqual( {
			store_api_nonce: 'store-api',
			tokenized_cart_nonce: 'tokenized',
			tokenized_cart_session_nonce: 'session',
		} );
	} );

	test( 'ignores responses that only rotate the Store API nonce', () => {
		const headers = new Headers();
		headers.append( 'Nonce', 'store-api' );

		rememberRefreshedNonces( headers );

		expect( getRefreshedNonces() ).toEqual( {} );
	} );

	test( 'ignores errors without headers', () => {
		rememberRefreshedNonces( undefined );

		expect( getRefreshedNonces() ).toEqual( {} );
	} );
} );
