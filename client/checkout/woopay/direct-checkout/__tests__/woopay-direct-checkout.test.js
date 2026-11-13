/**
 * Internal dependencies
 */
import WooPayDirectCheckout from '../woopay-direct-checkout';
import { redirectTo } from 'wcpay/utils/navigation';

// jsdom marks `window.location` unforgeable, so the redirect is asserted
// through the `redirectTo` helper the direct-checkout flow calls.
jest.mock( 'wcpay/utils/navigation', () => ( {
	redirectTo: jest.fn(),
} ) );

const MERCHANT_CHECKOUT_URL = 'https://merchant.test/checkout';
const WOOPAY_CHECKOUT_URL =
	'https://woopay.test/woopay?platform_checkout_key=1234567890';
const WOOPAY_MINIMUM_SESSION_URL =
	'https://woopay.test/woopay?checkout_redirect=1&blog_id=1&session=1&iv=1&hash=1';
// jsdom only implements hash navigation, so links whose click is expected to go through use a hash.
const UNHANDLED_CHECKOUT_HREF = '#checkout';

const flushPromises = () =>
	new Promise( ( resolve ) => setImmediate( resolve ) );

const click = ( element ) => {
	const event = new MouseEvent( 'click', {
		bubbles: true,
		cancelable: true,
	} );
	element.dispatchEvent( event );
	return event;
};

describe( 'WooPayDirectCheckout', () => {
	describe( 'addRedirectToWooPayEventListener', () => {
		let classicCheckoutButton;

		const appendMiniCartButton = ( href = MERCHANT_CHECKOUT_URL ) => {
			const button = document.createElement( 'a' );
			button.href = href;
			button.className = 'wc-block-mini-cart__footer-checkout';
			document.body.appendChild( button );
			return button;
		};

		beforeEach( () => {
			classicCheckoutButton = document.createElement( 'a' );
			classicCheckoutButton.href = MERCHANT_CHECKOUT_URL;
			classicCheckoutButton.classList.add( 'checkout-button' );
			classicCheckoutButton.innerText = 'Proceed to checkout';

			const divContainer = document.createElement( 'div' );
			divContainer.classList.add( 'wc-proceed-to-checkout' );
			divContainer.appendChild( classicCheckoutButton );

			document.body.appendChild( divContainer );

			jest.spyOn( WooPayDirectCheckout, 'teardown' );
			jest.spyOn(
				WooPayDirectCheckout,
				'getWooPayCheckoutUrl'
			).mockResolvedValue( WOOPAY_CHECKOUT_URL );
			jest.spyOn(
				WooPayDirectCheckout,
				'getWooPayMinimumSessionUrl'
			).mockResolvedValue( WOOPAY_MINIMUM_SESSION_URL );
			jest.spyOn(
				WooPayDirectCheckout,
				'isWooPayReachable'
			).mockResolvedValue( true );
		} );

		afterEach( () => {
			WooPayDirectCheckout.teardown();
			document.body.replaceChildren();
			jest.restoreAllMocks();
			jest.clearAllMocks();
		} );

		it( 'redirects a Cart block button rendered after the redirect was enabled', async () => {
			WooPayDirectCheckout.addRedirectToWooPayEventListener( true );

			const container = document.createElement( 'div' );
			container.innerHTML =
				'<div class="wp-block-woocommerce-proceed-to-checkout-block">' +
				'<a href="https://merchant.test/checkout">Proceed to checkout</a></div>';
			document.body.appendChild( container );

			click( container.querySelector( 'a' ) );
			await flushPromises();

			expect(
				WooPayDirectCheckout.getWooPayCheckoutUrl
			).toHaveBeenCalledTimes( 1 );
			expect( redirectTo ).toHaveBeenCalledWith(
				'https://woopay.test/woopay?platform_checkout_key=1234567890'
			);
		} );

		it( 'redirects a new Mini-Cart button when third-party cookies are unavailable', async () => {
			WooPayDirectCheckout.addRedirectToWooPayEventListener( false );
			const miniCartButton = appendMiniCartButton();

			click( miniCartButton );
			await flushPromises();

			expect( redirectTo ).toHaveBeenCalledWith(
				'https://woopay.test/woopay?checkout_redirect=1&blog_id=1&session=1&iv=1&hash=1'
			);
		} );

		it( 'handles a click once when the redirect is enabled again', async () => {
			WooPayDirectCheckout.addRedirectToWooPayEventListener( true );
			WooPayDirectCheckout.addRedirectToWooPayEventListener( true );

			click( classicCheckoutButton );
			await flushPromises();

			expect(
				WooPayDirectCheckout.getWooPayCheckoutUrl
			).toHaveBeenCalledTimes( 1 );
		} );

		it( 'uses the login state from the latest call', async () => {
			WooPayDirectCheckout.addRedirectToWooPayEventListener( false );
			WooPayDirectCheckout.addRedirectToWooPayEventListener( true );

			click( classicCheckoutButton );
			await flushPromises();

			expect(
				WooPayDirectCheckout.getWooPayMinimumSessionUrl
			).not.toHaveBeenCalled();
			expect( redirectTo ).toHaveBeenCalledWith(
				'https://woopay.test/woopay?platform_checkout_key=1234567890'
			);
		} );

		it( 'ignores further checkout clicks while a redirect is in progress', async () => {
			let resolveCheckoutUrl;
			WooPayDirectCheckout.getWooPayCheckoutUrl.mockReturnValue(
				new Promise( ( resolve ) => {
					resolveCheckoutUrl = resolve;
				} )
			);
			WooPayDirectCheckout.addRedirectToWooPayEventListener( true );
			const miniCartButton = appendMiniCartButton();

			click( classicCheckoutButton );
			const secondClick = click( miniCartButton );
			resolveCheckoutUrl( WOOPAY_CHECKOUT_URL );
			await flushPromises();

			expect( secondClick.defaultPrevented ).toBe( true );
			expect(
				WooPayDirectCheckout.getWooPayCheckoutUrl
			).toHaveBeenCalledTimes( 1 );
			expect( redirectTo ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'does not redirect a click that WooCommerce already prevented', async () => {
			WooPayDirectCheckout.addRedirectToWooPayEventListener( true );
			const miniCartButton = appendMiniCartButton();
			// The Mini-Cart prevents checkout like this while the cart is updating.
			miniCartButton.addEventListener( 'click', ( event ) =>
				event.preventDefault()
			);

			click( miniCartButton );
			await flushPromises();

			expect(
				WooPayDirectCheckout.getWooPayCheckoutUrl
			).not.toHaveBeenCalled();
			expect( redirectTo ).not.toHaveBeenCalled();
		} );

		it( 'does not redirect a disabled checkout button', async () => {
			WooPayDirectCheckout.addRedirectToWooPayEventListener( true );
			const miniCartButton = appendMiniCartButton(
				UNHANDLED_CHECKOUT_HREF
			);
			miniCartButton.setAttribute( 'aria-disabled', 'true' );

			const event = click( miniCartButton );
			await flushPromises();

			expect( event.defaultPrevented ).toBe( false );
			expect(
				WooPayDirectCheckout.getWooPayCheckoutUrl
			).not.toHaveBeenCalled();
			expect( redirectTo ).not.toHaveBeenCalled();
		} );

		it( 'ignores clicks outside the checkout buttons', async () => {
			WooPayDirectCheckout.addRedirectToWooPayEventListener( true );
			const otherButton = document.createElement( 'button' );
			document.body.appendChild( otherButton );

			const event = click( otherButton );
			await flushPromises();

			expect( event.defaultPrevented ).toBe( false );
			expect(
				WooPayDirectCheckout.getWooPayCheckoutUrl
			).not.toHaveBeenCalled();
		} );

		it( 'stops redirecting after teardown', async () => {
			WooPayDirectCheckout.addRedirectToWooPayEventListener( true );
			WooPayDirectCheckout.teardown();
			const miniCartButton = appendMiniCartButton(
				UNHANDLED_CHECKOUT_HREF
			);

			const event = click( miniCartButton );
			await flushPromises();

			expect( event.defaultPrevented ).toBe( false );
			expect(
				WooPayDirectCheckout.getWooPayCheckoutUrl
			).not.toHaveBeenCalled();
			expect( redirectTo ).not.toHaveBeenCalled();
		} );

		it( 'should add loading spinner when shortcode cart button is clicked', () => {
			WooPayDirectCheckout.addRedirectToWooPayEventListener( false );

			click( classicCheckoutButton );

			expect(
				classicCheckoutButton.querySelector(
					'span.wc-block-components-spinner'
				)
			).not.toBeNull();
		} );

		it( 'should redirect not logged in user to WooPay minimum session URL', async () => {
			WooPayDirectCheckout.addRedirectToWooPayEventListener( false );

			click( classicCheckoutButton );
			await flushPromises();

			expect(
				WooPayDirectCheckout.getWooPayMinimumSessionUrl
			).toHaveBeenCalled();
			expect( WooPayDirectCheckout.teardown ).toHaveBeenCalled();
			expect( redirectTo ).toHaveBeenCalledWith(
				'https://woopay.test/woopay?checkout_redirect=1&blog_id=1&session=1&iv=1&hash=1'
			);
		} );

		it( 'should redirect logged in user to WooPay checkout URL', async () => {
			WooPayDirectCheckout.addRedirectToWooPayEventListener( true );

			click( classicCheckoutButton );
			await flushPromises();

			expect(
				WooPayDirectCheckout.getWooPayCheckoutUrl
			).toHaveBeenCalled();
			expect( WooPayDirectCheckout.teardown ).toHaveBeenCalled();
			expect( redirectTo ).toHaveBeenCalledWith(
				'https://woopay.test/woopay?platform_checkout_key=1234567890'
			);
		} );

		it( 'should redirect to merchant checkout if WooPay checkout URL is not available', async () => {
			// Throw an error to simulate a failure in getting the WooPay checkout URL.
			WooPayDirectCheckout.getWooPayCheckoutUrl.mockRejectedValue(
				new Error( 'Could not retrieve WooPay checkout URL.' )
			);
			jest.spyOn( console, 'warn' ).mockImplementation( () => {} );

			WooPayDirectCheckout.addRedirectToWooPayEventListener( true );

			click( classicCheckoutButton );
			await flushPromises();

			expect(
				WooPayDirectCheckout.getWooPayCheckoutUrl
			).toHaveBeenCalled();
			expect( WooPayDirectCheckout.teardown ).toHaveBeenCalled();
			expect( redirectTo ).toHaveBeenCalledWith(
				'https://merchant.test/checkout'
			);
		} );
	} );
} );
