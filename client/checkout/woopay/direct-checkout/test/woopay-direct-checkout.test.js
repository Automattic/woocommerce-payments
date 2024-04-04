/**
 * Internal dependencies
 */
import WooPayDirectCheckout from '../woopay-direct-checkout';

describe( 'WooPayDirectCheckout', () => {
	describe( 'redirectToWooPay', () => {
		const originalLocation = window.location;
		let elements;

		beforeEach( () => {
			delete window.location;
			window.location = { href: jest.fn() };

			const checkoutButton = document.createElement( 'a' );
			checkoutButton.href = 'https://merchant.test/checkout';
			checkoutButton.classList.add( 'checkout-button' );
			checkoutButton.innerText = 'Proceed to checkout';

			const divContainer = document.createElement( 'div' );
			divContainer.classList.add( 'wc-proceed-to-checkout' );
			divContainer.appendChild( checkoutButton );

			document.body.appendChild( divContainer );

			elements = document.querySelectorAll(
				WooPayDirectCheckout.redirectElements
					.CLASSIC_CART_PROCEED_BUTTON
			);

			WooPayDirectCheckout.teardown = jest.fn();
			WooPayDirectCheckout.getWooPayCheckoutUrl = jest.fn();
			WooPayDirectCheckout.getWooPayMinimumSessionUrl = jest.fn();
		} );

		afterEach( () => {
			window.location = originalLocation;
			elements.forEach( ( el ) => el.parentElement.remove() );
			jest.clearAllMocks();
		} );

		it( 'should add event listeners to provided "proceed to checkout" button elements', () => {
			elements.forEach( ( element ) => {
				element.addEventListener = jest.fn();
			} );

			WooPayDirectCheckout.redirectToWooPay( elements );

			elements.forEach( ( element ) => {
				expect( element.addEventListener ).toHaveBeenCalledWith(
					'click',
					expect.any( Function )
				);
			} );
		} );

		it( 'should add loading spinner when shortcode cart button is clicked', () => {
			WooPayDirectCheckout.redirectToWooPay( elements, false );

			elements[ 0 ].click();

			expect(
				elements[ 0 ].querySelector(
					'span.wc-block-components-spinner'
				)
			).not.toBeNull();
		} );

		it( 'should redirect not logged in user to WooPay minimum session URL', async () => {
			WooPayDirectCheckout.getWooPayMinimumSessionUrl.mockResolvedValue(
				'https://woopay.test/woopay?checkout_redirect=1&blog_id=1&session=1&iv=1&hash=1'
			);

			WooPayDirectCheckout.redirectToWooPay( elements, false );

			elements[ 0 ].click();

			await new Promise( ( resolve ) => setImmediate( resolve ) );

			expect(
				WooPayDirectCheckout.getWooPayMinimumSessionUrl
			).toHaveBeenCalled();
			expect( WooPayDirectCheckout.teardown ).toHaveBeenCalled();
			expect( window.location.href ).toBe(
				'https://woopay.test/woopay?checkout_redirect=1&blog_id=1&session=1&iv=1&hash=1'
			);
		} );

		it( 'should redirect logged in user to WooPay checkout URL', async () => {
			WooPayDirectCheckout.getWooPayCheckoutUrl.mockResolvedValue(
				'https://woopay.test/woopay?platform_checkout_key=1234567890'
			);

			WooPayDirectCheckout.redirectToWooPay( elements, true );

			elements[ 0 ].click();

			await new Promise( ( resolve ) => setImmediate( resolve ) );

			expect(
				WooPayDirectCheckout.getWooPayCheckoutUrl
			).toHaveBeenCalled();
			expect( WooPayDirectCheckout.teardown ).toHaveBeenCalled();
			expect( window.location.href ).toBe(
				'https://woopay.test/woopay?platform_checkout_key=1234567890'
			);
		} );

		it( 'should redirect to merchant checkout if WooPay checkout URL is not available', async () => {
			// Throw an error to simulate a failure in getting the WooPay checkout URL.
			WooPayDirectCheckout.getWooPayCheckoutUrl.mockRejectedValue(
				new Error( 'Could not retrieve WooPay checkout URL.' )
			);

			WooPayDirectCheckout.redirectToWooPay( elements, true );

			elements[ 0 ].click();

			await new Promise( ( resolve ) => setImmediate( resolve ) );

			expect(
				WooPayDirectCheckout.getWooPayCheckoutUrl
			).toHaveBeenCalled();
			expect( WooPayDirectCheckout.teardown ).toHaveBeenCalled();
			expect( window.location.href ).toBe(
				'https://merchant.test/checkout'
			);
		} );
	} );
} );
