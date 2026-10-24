const mockGetPreferredPaymentMethod = jest.fn();
const mockDetachMessageListener = jest.fn();
const mockUserConnectConstructor = jest.fn();

jest.mock( 'wcpay/checkout/woopay/connect/user-connect', () => {
	return jest.fn().mockImplementation( () => {
		mockUserConnectConstructor();
		return {
			getPreferredPaymentMethod: mockGetPreferredPaymentMethod,
			detachMessageListener: mockDetachMessageListener,
		};
	} );
} );

describe( 'fetchPreferredCard', () => {
	let fetchPreferredCard;

	beforeEach( () => {
		mockGetPreferredPaymentMethod.mockReset();
		mockDetachMessageListener.mockReset();
		mockUserConnectConstructor.mockReset();
		// Reset the module-scoped cache between tests so each test starts fresh.
		jest.isolateModules( () => {
			( { fetchPreferredCard } = require( '../preferred-card-fetch' ) );
		} );
	} );

	test( 'returns valid card from iframe', async () => {
		mockGetPreferredPaymentMethod.mockResolvedValue( {
			brand: 'mastercard',
			last4: '5555',
		} );
		const result = await fetchPreferredCard();
		expect( result ).toEqual( {
			brand: 'mastercard',
			last4: '5555',
		} );
		expect( mockDetachMessageListener ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'returns null when iframe returns invalid data', async () => {
		mockGetPreferredPaymentMethod.mockResolvedValue( {
			brand: 'visa',
		} );
		const result = await fetchPreferredCard();
		expect( result ).toBeNull();
		expect( mockDetachMessageListener ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'returns null when iframe returns null', async () => {
		mockGetPreferredPaymentMethod.mockResolvedValue( null );
		const result = await fetchPreferredCard();
		expect( result ).toBeNull();
		expect( mockDetachMessageListener ).toHaveBeenCalledTimes( 1 );
	} );

	// Defensive coverage. WooPayUserConnect.getPreferredPaymentMethod swallows
	// its own errors today, but if that contract ever changes (or the
	// underlying postMessage call throws synchronously), the listener still
	// needs to be detached so subsequent loads don't leak handlers.
	test( 'detaches listener if the connect call rejects (defensive)', async () => {
		mockGetPreferredPaymentMethod.mockRejectedValue(
			new Error( 'timeout' )
		);
		await expect( fetchPreferredCard() ).rejects.toThrow( 'timeout' );
		expect( mockDetachMessageListener ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'caches the result for the lifetime of the page', async () => {
		mockGetPreferredPaymentMethod.mockResolvedValue( {
			brand: 'visa',
			last4: '4242',
		} );

		// Concurrent callers share the inflight promise.
		const [ a, b ] = await Promise.all( [
			fetchPreferredCard(),
			fetchPreferredCard(),
		] );
		// Subsequent callers after settlement reuse the cached promise.
		const c = await fetchPreferredCard();

		expect( a ).toEqual( { brand: 'visa', last4: '4242' } );
		expect( b ).toEqual( { brand: 'visa', last4: '4242' } );
		expect( c ).toEqual( { brand: 'visa', last4: '4242' } );
		expect( mockUserConnectConstructor ).toHaveBeenCalledTimes( 1 );
		expect( mockGetPreferredPaymentMethod ).toHaveBeenCalledTimes( 1 );
	} );
} );
