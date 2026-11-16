import { handleWooPayEmailInput } from '../email-input-iframe';
import request from 'wcpay/checkout/utils/request';
import { getConfig } from 'wcpay/utils/checkout';
import WooPayFirstPartyAuth from 'wcpay/checkout/woopay/express-button/woopay-first-party-auth';

jest.mock( 'wcpay/checkout/utils/request' );
jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );
jest.mock( 'tracks', () => ( {
	getTracksIdentity: jest.fn().mockResolvedValue( null ),
	recordUserEvent: jest.fn(),
} ) );
jest.mock(
	'wcpay/checkout/woopay/express-button/woopay-first-party-auth',
	() => ( {
		__esModule: true,
		default: {
			init: jest.fn(),
			getWooPaySessionFromMerchant: jest.fn(),
			sendPreemptiveSessionDataToWooPay: jest.fn(),
		},
	} )
);

describe( 'WooPay email input', () => {
	const session = { blog_id: 1, data: { session: 'checkout-session' } };

	beforeEach( () => {
		document.body.innerHTML =
			'<form name="checkout"><div><input id="billing_email" /></div></form>';
		window.wcpayConfig = {
			pay_for_order: 'false',
			woopayIsCountryAvailable: true,
		};
		Object.defineProperty( performance, 'getEntriesByType', {
			configurable: true,
			value: jest.fn().mockReturnValue( [ { type: 'navigate' } ] ),
		} );
		getConfig.mockImplementation( ( key ) => {
			if ( key === 'woopayHost' ) {
				return 'https://woopay.example';
			}
			if ( key === 'isWoopayFirstPartyAuthEnabled' ) {
				return true;
			}
			return 'test-value';
		} );
		request.mockResolvedValue( {
			success: true,
			data: { signature: 'sig' },
		} );
		global.fetch = jest.fn().mockResolvedValue( {
			status: 200,
			json: () => Promise.resolve( { 'user-exists': true } ),
		} );
		WooPayFirstPartyAuth.getWooPaySessionFromMerchant.mockResolvedValue(
			session
		);
		WooPayFirstPartyAuth.sendPreemptiveSessionDataToWooPay.mockResolvedValue(
			{
				redirect_url:
					'https://woopay.example/woopay/?platform_checkout_key=key',
			}
		);
	} );

	afterEach( () => {
		delete performance.getEntriesByType;
		jest.restoreAllMocks();
		jest.clearAllMocks();
		document.body.innerHTML = '';
	} );

	test( 'offers first-party checkout for a registered email without opening the OTP iframe', async () => {
		const emailInput = document.querySelector( '#billing_email' );
		emailInput.value = 'shopper@example.com';

		await handleWooPayEmailInput( '#billing_email', {} );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		const continueButton = document.querySelector(
			'.woopay-email-continue-button'
		);
		expect( continueButton.hidden ).toBe( false );
		expect( document.querySelector( '.woopay-otp-iframe' ) ).toBeNull();

		continueButton.click();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect(
			WooPayFirstPartyAuth.getWooPaySessionFromMerchant
		).toHaveBeenCalledWith(
			expect.objectContaining( { email: 'shopper@example.com' } )
		);
		expect(
			WooPayFirstPartyAuth.sendPreemptiveSessionDataToWooPay
		).toHaveBeenCalledWith( session );
	} );

	test( 'keeps checkout available when a first-party session cannot be created', async () => {
		const emailInput = document.querySelector( '#billing_email' );
		emailInput.value = 'shopper@example.com';
		WooPayFirstPartyAuth.getWooPaySessionFromMerchant.mockResolvedValue(
			{}
		);

		await handleWooPayEmailInput( '#billing_email', {} );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		const continueButton = document.querySelector(
			'.woopay-email-continue-button'
		);
		continueButton.click();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( continueButton.disabled ).toBe( false );
		expect( document.querySelector( '.woopay-otp-iframe' ) ).toBeNull();
		expect(
			document.querySelector(
				'.wc-block-checkout__guest-checkout-notice'
			)
		).not.toBeNull();
	} );

	test( 'uses the OTP iframe when first-party checkout is unavailable', async () => {
		getConfig.mockImplementation( ( key ) => {
			if ( key === 'isWoopayFirstPartyAuthEnabled' ) {
				return false;
			}
			if ( key === 'woopayHost' ) {
				return 'https://woopay.example';
			}
			return 'test-value';
		} );

		await handleWooPayEmailInput( '#billing_email', {} );
		const emailInput = document.querySelector( '#billing_email' );
		emailInput.value = 'shopper@example.com';
		emailInput.dispatchEvent( new Event( 'input' ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 550 ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect(
			document.querySelector( '.woopay-email-continue-button' )
		).toBeNull();
		expect( document.querySelector( '.woopay-otp-iframe' ) ).not.toBeNull();
	} );
} );
