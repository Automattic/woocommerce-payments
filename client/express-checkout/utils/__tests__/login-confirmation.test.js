/**
 * Internal dependencies
 */
import { displayLoginConfirmation } from '..';

describe( 'displayLoginConfirmation', () => {
	let confirmSpy;
	const originalLocation = window.location;

	beforeEach( () => {
		confirmSpy = jest.spyOn( window, 'confirm' );
		delete window.location;
		window.location = { href: '' };
	} );

	afterEach( () => {
		confirmSpy.mockRestore();
		window.location = originalLocation;
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

		expect( window.location.href ).toBe( '/my-account' );
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

		expect( window.location.href ).toBe( '' );
	} );
} );
