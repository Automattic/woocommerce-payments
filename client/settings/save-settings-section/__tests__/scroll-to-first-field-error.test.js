/**
 * Internal dependencies
 */
import scrollToFirstFieldError from '../scroll-to-first-field-error';

describe( 'scrollToFirstFieldError()', () => {
	beforeEach( () => {
		window.matchMedia = jest.fn().mockReturnValue( { matches: false } );
	} );

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'scrolls to and focuses the input for the first field in details', () => {
		document.body.innerHTML =
			'<input id="account-business-support-phone-input" />';
		const element = document.getElementById(
			'account-business-support-phone-input'
		);
		element.scrollIntoView = jest.fn();
		element.focus = jest.fn();

		scrollToFirstFieldError( {
			account_business_support_phone: {
				code: 'wcpay_failed_to_update_stripe_account',
				message: 'Invalid phone number.',
				data: null,
			},
		} );

		expect( element.scrollIntoView ).toHaveBeenCalledWith( {
			behavior: 'smooth',
			block: 'center',
		} );
		expect( element.focus ).toHaveBeenCalledWith( { preventScroll: true } );
	} );

	it( 'uses reduced motion when prefers-reduced-motion is set', () => {
		window.matchMedia = jest.fn().mockReturnValue( { matches: true } );
		document.body.innerHTML =
			'<input id="account-business-support-phone-input" />';
		const element = document.getElementById(
			'account-business-support-phone-input'
		);
		element.scrollIntoView = jest.fn();
		element.focus = jest.fn();

		scrollToFirstFieldError( {
			account_business_support_phone: { message: 'Invalid.' },
		} );

		expect( element.scrollIntoView ).toHaveBeenCalledWith( {
			behavior: 'auto',
			block: 'center',
		} );
	} );

	it( 'falls back to the hyphenated convention for any field key', () => {
		document.body.innerHTML = '<input id="account-business-name-input" />';
		const element = document.getElementById(
			'account-business-name-input'
		);
		element.scrollIntoView = jest.fn();
		element.focus = jest.fn();

		scrollToFirstFieldError( {
			account_business_name: { message: 'Invalid name.' },
		} );

		expect( element.scrollIntoView ).toHaveBeenCalledWith( {
			behavior: 'smooth',
			block: 'center',
		} );
		expect( element.focus ).toHaveBeenCalledWith( { preventScroll: true } );
	} );

	it.each( [ undefined, null, 'oops', {} ] )(
		'no-ops on %p details',
		( details ) => {
			expect( () => scrollToFirstFieldError( details ) ).not.toThrow();
		}
	);

	it( 'no-ops when no matching element exists in the DOM', () => {
		expect( () =>
			scrollToFirstFieldError( {
				account_business_support_phone: { message: 'Invalid.' },
			} )
		).not.toThrow();
	} );
} );
