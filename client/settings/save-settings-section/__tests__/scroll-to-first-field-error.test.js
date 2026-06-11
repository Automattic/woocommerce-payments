/**
 * Internal dependencies
 */
import scrollToFirstFieldError from '../scroll-to-first-field-error';

describe( 'scrollToFirstFieldError()', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'scrolls to the input mapped to the first field in details', () => {
		document.body.innerHTML =
			'<input id="account-business-support-phone-input" />';
		const element = document.getElementById(
			'account-business-support-phone-input'
		);
		element.scrollIntoView = jest.fn();

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
	} );

	it( 'falls back to the hyphenated convention for unmapped fields', () => {
		document.body.innerHTML = '<input id="account-business-name-input" />';
		const element = document.getElementById(
			'account-business-name-input'
		);
		element.scrollIntoView = jest.fn();

		scrollToFirstFieldError( {
			account_business_name: { message: 'Invalid name.' },
		} );

		expect( element.scrollIntoView ).toHaveBeenCalled();
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
