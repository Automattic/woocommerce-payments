/**
 * Internal dependencies
 */
import { displayLoginConfirmation } from '../login-confirmation';
import { redirectTo } from 'wcpay/utils/navigation';

// jsdom marks `window.location` unforgeable, so the redirect is asserted
// through the `redirectTo` helper instead of the location itself.
jest.mock( 'wcpay/utils/navigation', () => ( {
	redirectTo: jest.fn(),
} ) );

describe( 'displayLoginConfirmation', () => {
	let confirmSpy;

	beforeEach( () => {
		confirmSpy = jest.spyOn( window, 'confirm' );
		redirectTo.mockClear();
	} );

	afterEach( () => {
		confirmSpy.mockRestore();
		delete window.wcpayExpressCheckoutParams;
	} );

	test( 'does nothing when login_confirmation is false', () => {
		window.wcpayExpressCheckoutParams = {
			login_confirmation: false,
		};

		displayLoginConfirmation( 'apple_pay' );

		expect( confirmSpy ).not.toHaveBeenCalled();
	} );

	test( 'shows confirm dialog with payment type name replacing bold placeholder', () => {
		window.wcpayExpressCheckoutParams = {
			login_confirmation: {
				message: 'To pay with **express checkout**, you must log in.',
				redirect_url: '/my-account',
			},
		};

		confirmSpy.mockReturnValue( false );
		displayLoginConfirmation( 'google_pay' );

		expect( confirmSpy ).toHaveBeenCalledWith(
			'To pay with Google Pay, you must log in.'
		);
	} );

	test( 'redirects when user confirms', () => {
		window.wcpayExpressCheckoutParams = {
			login_confirmation: {
				message: '**express checkout**',
				redirect_url: '/my-account',
			},
		};

		confirmSpy.mockReturnValue( true );
		displayLoginConfirmation( 'apple_pay' );

		expect( redirectTo ).toHaveBeenCalledWith( '/my-account' );
	} );

	test( 'does not redirect when user cancels', () => {
		window.wcpayExpressCheckoutParams = {
			login_confirmation: {
				message: '**express checkout**',
				redirect_url: '/my-account',
			},
		};

		confirmSpy.mockReturnValue( false );
		displayLoginConfirmation( 'apple_pay' );

		expect( redirectTo ).not.toHaveBeenCalled();
	} );
} );
