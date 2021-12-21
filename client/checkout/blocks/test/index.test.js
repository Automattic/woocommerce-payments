/**
 * External dependencies
 */
// eslint-disable-next-line import/no-unresolved
import { registerExpressPaymentMethod } from '@woocommerce/blocks-registry';

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/checkout';

jest.mock(
	'@woocommerce/blocks-registry',
	() => ( {
		registerExpressPaymentMethod: jest.fn(),
		registerPaymentMethod: jest.fn(),
	} ),
	{ virtual: true }
);
jest.mock( 'wcpay/components/platform-checkout', () => ( {
	platformCheckoutPaymentMethod: jest.fn( () => 'foo' ),
} ) );
jest.mock( 'utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

describe( 'wcpay/checkout/blocks/index.js', () => {
	afterEach( () => {
		jest.resetAllMocks();
	} );

	test( 'registers platform checkout if isPlatformCheckoutEnabled is true', async () => {
		getConfig.mockImplementation( ( setting ) =>
			'isPlatformCheckoutEnabled' === setting ? true : false
		);
		await import( '..' );
		expect( registerExpressPaymentMethod ).toHaveBeenCalledWith( 'foo' );
	} );

	test( 'does not register platform checkout if isPlatformCheckoutEnabled is false', async () => {
		getConfig.mockImplementation( () => false );
		await import( '..' );
		expect( registerExpressPaymentMethod ).not.toHaveBeenCalledWith(
			'foo'
		);
	} );
} );
