/**
 * External dependencies
 */
import { act, render, screen, waitFor } from '@testing-library/react';
import $ from 'jquery';
import { recordUserEvent } from 'tracks';
import apiFetch from '@wordpress/api-fetch';
import { speak } from '@wordpress/a11y';
import {
	cartWithItemsMock,
	cartWithItemsAndCouponMock,
} from '../../__fixtures__/cart';

jest.mock( 'tracks', () => ( {
	recordUserEvent: jest.fn(),
} ) );
jest.mock( '@wordpress/a11y', () => ( {
	speak: jest.fn(),
} ) );
jest.mock( 'lodash', () => ( {
	...jest.requireActual( 'lodash' ),
	debounce: jest.fn( ( callback ) => callback ),
} ) );
jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

describe( 'Tokenized Express Checkout Element - Shortcode checkout page logic', () => {
	let stripeElementMock, stripeInstance;

	// These tests do not send a real order-review request.
	// This helper emits the same Ajax events as WooCommerce.
	const orderReviewRequest = ( url ) => {
		const settings = {
			url:
				url ??
				global.wc_checkout_params.wc_ajax_url.replace(
					'%%endpoint%%',
					'update_order_review'
				),
		};

		return {
			send: () => $( document ).trigger( 'ajaxSend', [ {}, settings ] ),
			fail: ( status = 500 ) => {
				$( document ).trigger( 'ajaxError', [ { status }, settings ] );
				$( document ).trigger( 'ajaxComplete', [
					{ status },
					settings,
				] );
			},
			succeed: () =>
				$( document ).trigger( 'ajaxComplete', [
					{ status: 200 },
					settings,
				] ),
			abort: () => {
				$( document ).trigger( 'ajaxError', [
					{ status: 0, statusText: 'abort' },
					settings,
				] );
				$( document ).trigger( 'ajaxComplete', [
					{ status: 0 },
					settings,
				] );
			},
		};
	};
	beforeEach( () => {
		apiFetch.mockClear();
		apiFetch.mockImplementation( async () =>
			Promise.resolve( {
				json: () => Promise.resolve( cartWithItemsMock ),
				headers: new Map(),
			} )
		);
		// ensuring jQuery is available globally.
		global.$ = global.jQuery = $;
		// ensuring that `callback` is immediately invoked on document.ready.
		$.fn.ready = ( callback ) => callback( $ );
		global.jQuery.blockUI = () => null;
		global.jQuery.unblockUI = () => null;
		// The element-level half of jquery-blockui, which `button-ui` uses to
		// overlay the button. Spies, so tests can assert on the overlay.
		$.fn.block = jest.fn( function () {
			this.data( 'blockUI.isBlocked', 1 );
			return this;
		} );
		$.fn.unblock = jest.fn( function () {
			this.data( 'blockUI.isBlocked', 0 );
			return this;
		} );

		global.wc_checkout_params = { wc_ajax_url: '/?wc-ajax=%%endpoint%%' };

		global.wcpayExpressCheckoutParams = {};
		global.wcpayExpressCheckoutParams.nonce = {
			store_api_nonce: 'store_api_nonce',
		};
		global.wcpayExpressCheckoutParams.stripe = {
			accountId: 'acc_id',
			locale: 'it',
			publishableKey: 'stripe_public_key',
		};
		global.wcpayExpressCheckoutParams.checkout = {
			country_code: 'US',
			currency_code: 'usd',
			currency_decimals: 2,
			needs_payer_phone: false,
			needs_shipping: true,
			allowed_shipping_countries: [ 'US' ],
		};
		global.wcpayExpressCheckoutParams.store_name = 'My fancy store';
		global.wcpayExpressCheckoutParams.button_context = 'checkout';
		global.wcpayExpressCheckoutParams.enabled_methods = [
			'payment_request',
		];

		// just mocking some server-side-provided DOM elements.
		render(
			<div>
				<div className="woocommerce-notices-wrapper" />
				<div id="wcpay-express-checkout-wrapper">
					<div
						id="wcpay-express-checkout-element"
						data-testid="wcpay-express-checkout-element"
					/>
				</div>
			</div>
		);

		const stripeElementRegisteredEventCallbacks = {};
		stripeElementMock = {
			submit: jest.fn(),
			mount: jest.fn(),
			unmount: jest.fn(),
			__getRegisteredEvent: ( eventName ) =>
				stripeElementRegisteredEventCallbacks[ eventName ],
			on: jest.fn( ( event, callback ) => {
				stripeElementRegisteredEventCallbacks[ event ] = callback;
			} ),
		};
		global.Stripe = jest.fn( () => {
			stripeInstance = {
				elements: jest.fn( () => ( {
					create: jest.fn( () => stripeElementMock ),
				} ) ),
			};

			return stripeInstance;
		} );
	} );

	afterEach( async () => {
		delete global.Stripe;
		delete global.wc_checkout_params;
		// removing all the registered event handlers so they don't leak between tests.
		// jQuery's global ajax events are bound on `document`, not `document.body`.
		global.$( document.body ).off();
		global.$( document ).off();
	} );

	it( 'should not initialize Stripe if there is no publishable key', async () => {
		global.wcpayExpressCheckoutParams.stripe.publishableKey = '';

		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		expect( global.Stripe ).not.toHaveBeenCalled();
		expect( recordUserEvent ).not.toHaveBeenCalled();
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).not.toHaveClass( 'is-ready' );
	} );

	it( 'should track the initialization', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		expect( global.Stripe ).not.toHaveBeenCalled();

		$( document.body ).trigger( 'updated_checkout' );

		await waitFor( () =>
			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					method: 'GET',
					path: expect.stringContaining( '/wc/store/v1/cart' ),
				} )
			)
		);
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );
		expect( stripeInstance.elements ).toHaveBeenCalledWith( {
			mode: 'payment',
			amount: 3697,
			currency: 'usd',
			appearance: expect.anything(),
			locale: 'it',
			paymentMethodTypes: [ 'card' ],
		} );

		// triggering the `ready` event on the ECE button, to test its callback.
		stripeElementMock.__getRegisteredEvent( 'ready' )( {
			availablePaymentMethods: {
				link: false,
				applePay: true,
				googlePay: true,
				paypal: false,
				amazonPay: false,
			},
		} );

		expect( recordUserEvent ).toHaveBeenNthCalledWith(
			1,
			'applepay_button_load',
			expect.objectContaining( { source: 'checkout' } )
		);
		expect( recordUserEvent ).toHaveBeenNthCalledWith(
			2,
			'gpay_button_load',
			expect.objectContaining( { source: 'checkout' } )
		);
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toBeVisible();
	} );

	it( 'should ensure that new cart totals are fetched on the `updated_checkout` event', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );

		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toBeVisible();

		// triggering the `click` event on the ECE button, to test its callback.
		const clickEventResolveMock = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: clickEventResolveMock,
			expressPaymentType: 'google_pay',
		} );

		expect( clickEventResolveMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [
					{ amount: 2399, name: 'Beanie' },
					{ amount: 1100, name: 'Shipping' },
					{ amount: 198, name: 'Tax' },
				],
				shippingAddressRequired: true,
				shippingRates: [
					{
						amount: 1100,
						deliveryEstimate: '',
						displayName: 'Flat rate',
						id: 'flat_rate:1',
					},
					{
						amount: 2200,
						deliveryEstimate: '',
						displayName: 'Express shipping',
						id: 'flat_rate:5',
					},
				],
			} )
		);

		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: clickEventResolveMock,
			expressPaymentType: 'google_pay',
		} );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );

		apiFetch.mockImplementation( async () =>
			Promise.resolve( {
				json: () => Promise.resolve( cartWithItemsAndCouponMock ),
				headers: new Map(),
			} )
		);

		// with the new API response, trigger `updated_checkout` again.
		$( document.body ).trigger( 'updated_checkout' );

		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 2 ) );

		// since this time the totals should be `0`, there should be no button.
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).not.toBeVisible();
	} );

	it( 'should reject the click event while a cart refresh is in flight', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );

		// Hold the second cart response pending, to sit inside the re-init window.
		let releaseCart;
		apiFetch.mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					releaseCart = () =>
						resolve( {
							json: () => Promise.resolve( cartWithItemsMock ),
							headers: new Map(),
						} );
				} )
		);
		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 2 ) );

		// The button is still mounted and visible, so it can still be clicked here.
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toBeVisible();

		const clickEventResolveMock = jest.fn();
		const clickEventRejectMock = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: clickEventResolveMock,
			reject: clickEventRejectMock,
			expressPaymentType: 'google_pay',
		} );

		expect( clickEventRejectMock ).toHaveBeenCalledTimes( 1 );
		expect( clickEventResolveMock ).not.toHaveBeenCalled();
		// The overlay is visual only; screen-reader users get told why the tap did nothing.
		expect( speak ).toHaveBeenCalledWith(
			'Updating payment details. Please try again in a moment.',
			'assertive'
		);

		releaseCart();
		await waitFor( () =>
			expect( stripeInstance.elements ).toHaveBeenCalledTimes( 2 )
		);

		// Once the refresh completes, the newly initialized button can open
		// against the fresh cart data.
		const postRefreshResolveMock = jest.fn();
		const postRefreshRejectMock = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: postRefreshResolveMock,
			reject: postRefreshRejectMock,
			expressPaymentType: 'google_pay',
		} );

		expect( postRefreshResolveMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				shippingAddressRequired: true,
				shippingRates: expect.any( Array ),
			} )
		);
		expect( postRefreshRejectMock ).not.toHaveBeenCalled();
	} );

	it( 'should not let a superseded refresh publish its cart data or remount Elements', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );

		// Refresh A is slow and carries the coupon cart, whose total is 0. If A
		// ever published, the button would hide - which is what makes this
		// observable rather than a no-op assertion.
		let releaseSlowRefresh;
		let slowResponseConsumed = false;
		apiFetch.mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					releaseSlowRefresh = () =>
						resolve( {
							json: () => {
								slowResponseConsumed = true;
								return Promise.resolve(
									cartWithItemsAndCouponMock
								);
							},
							headers: new Map(),
						} );
				} )
		);
		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 2 ) );

		// Refresh B starts after A and answers first, with a payable cart.
		apiFetch.mockImplementation( async () =>
			Promise.resolve( {
				json: () => Promise.resolve( cartWithItemsMock ),
				headers: new Map(),
			} )
		);
		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 3 ) );

		const elementsGenerationsAfterB =
			stripeInstance.elements.mock.calls.length;
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toBeVisible();

		// Release A and synchronize on A actually resuming past its fetch, so the
		// assertions below run after A had its chance to publish.
		releaseSlowRefresh();
		await waitFor( () => expect( slowResponseConsumed ).toBe( true ) );
		await act( async () => {
			await Promise.resolve();
		} );

		// A's zero-total cart was discarded: the button is still B's.
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toBeVisible();
		expect( stripeInstance.elements.mock.calls.length ).toBe(
			elementsGenerationsAfterB
		);

		const clickEventResolveMock = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: clickEventResolveMock,
			reject: jest.fn(),
			expressPaymentType: 'google_pay',
		} );
		expect( clickEventResolveMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [
					{ amount: 2399, name: 'Beanie' },
					{ amount: 1100, name: 'Shipping' },
					{ amount: 198, name: 'Tax' },
				],
			} )
		);
	} );

	it( 'should not let a refresh superseded while Stripe.js is still loading build Elements over the newer one', async () => {
		// `getStripe()` polls `window.Stripe` every 100 ms until Stripe.js has
		// loaded, so a refresh can be superseded while it waits there, after it
		// has already published its cart data.
		jest.useFakeTimers();
		const StripeConstructor = global.Stripe;
		delete global.Stripe;

		try {
			// Refresh A carries a different total than B, so the generation
			// that ends up owning Elements is observable.
			apiFetch.mockImplementation( async () =>
				Promise.resolve( {
					json: () =>
						Promise.resolve( {
							...cartWithItemsMock,
							totals: {
								...cartWithItemsMock.totals,
								total_price: '5000',
							},
						} ),
					headers: new Map(),
				} )
			);
			await jest.isolateModulesAsync( async () => {
				await import( '..' );
			} );
			$( document.body ).trigger( 'updated_checkout' );
			await act( async () => {
				await Promise.resolve();
			} );

			// Refresh B starts 50 ms later, so its poll ticks between A's.
			apiFetch.mockImplementation( async () =>
				Promise.resolve( {
					json: () => Promise.resolve( cartWithItemsMock ),
					headers: new Map(),
				} )
			);
			await act( async () => {
				jest.advanceTimersByTime( 50 );
			} );
			$( document.body ).trigger( 'updated_checkout' );
			await act( async () => {
				await Promise.resolve();
			} );

			// Stripe.js lands right after A's tick at 100 ms found it missing:
			// B's tick at 150 ms proceeds first, A's at 200 ms resumes last.
			await act( async () => {
				jest.advanceTimersByTime( 60 );
			} );
			global.Stripe = StripeConstructor;
			await act( async () => {
				jest.advanceTimersByTime( 50 );
			} );
			expect( stripeInstance.elements ).toHaveBeenCalledTimes( 1 );
			await act( async () => {
				jest.advanceTimersByTime( 100 );
			} );

			// Only B built and mounted a generation; A's stale total never did.
			expect( stripeInstance.elements ).toHaveBeenCalledTimes( 1 );
			expect( stripeInstance.elements ).toHaveBeenCalledWith(
				expect.objectContaining( { amount: 3697 } )
			);
			expect( stripeElementMock.mount ).toHaveBeenCalledTimes( 1 );
		} finally {
			jest.useRealTimers();
		}
	} );

	it( 'should hide the button, lift the overlay, and reject clicks when the cart data could not be fetched', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );
		$.fn.unblock.mockClear();

		// A failed refresh leaves the button with nothing to describe the purchase with.
		apiFetch.mockImplementation( async () =>
			Promise.reject( new Error( 'Store API is unavailable' ) )
		);
		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () =>
			expect(
				screen.getByTestId( 'wcpay-express-checkout-element' )
			).not.toBeVisible()
		);
		// The overlay must not be left latched on the hidden container:
		// `blockButton()` would then skip the next refresh's overlay.
		await waitFor( () => expect( $.fn.unblock ).toHaveBeenCalled() );

		// The element from the previous init can still invoke its handler - it must not throw.
		const clickEventResolveMock = jest.fn();
		const clickEventRejectMock = jest.fn();
		expect( () =>
			stripeElementMock.__getRegisteredEvent( 'click' )( {
				resolve: clickEventResolveMock,
				reject: clickEventRejectMock,
				expressPaymentType: 'google_pay',
			} )
		).not.toThrow();
		expect( clickEventResolveMock ).not.toHaveBeenCalled();
		expect( clickEventRejectMock ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'should overlay the button while a cart refresh is in flight and lift it once the refresh settles', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );
		$.fn.block.mockClear();
		$.fn.unblock.mockClear();

		let releaseCart;
		apiFetch.mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					releaseCart = () =>
						resolve( {
							json: () => Promise.resolve( cartWithItemsMock ),
							headers: new Map(),
						} );
				} )
		);
		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 2 ) );

		// Same white overlay WooCommerce paints over the order review.
		expect( $.fn.block ).toHaveBeenCalledWith( {
			message: null,
			overlayCSS: { background: '#fff', opacity: 0.6 },
		} );
		expect( $.fn.unblock ).not.toHaveBeenCalled();
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toHaveAttribute( 'aria-busy', 'true' );

		releaseCart();
		await waitFor( () => expect( $.fn.unblock ).toHaveBeenCalled() );
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toBeVisible();
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).not.toHaveAttribute( 'aria-busy' );
	} );

	it( 'should keep the overlay up until the newest refresh settles, even if a superseded one finishes first', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );

		const releases = [];
		apiFetch.mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					releases.push( () =>
						resolve( {
							json: () => Promise.resolve( cartWithItemsMock ),
							headers: new Map(),
						} )
					);
				} )
		);
		$.fn.block.mockClear();
		$( document.body ).trigger( 'updated_checkout' );
		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 3 ) );
		$.fn.unblock.mockClear();

		// B found the button already covered by A: re-blocking would blink the overlay.
		expect( $.fn.block ).toHaveBeenCalledTimes( 1 );

		// A (superseded) settles first: B still owns the button, so no unblock.
		releases[ 0 ]();
		await act( async () => {
			await Promise.resolve();
		} );
		expect( $.fn.unblock ).not.toHaveBeenCalled();

		releases[ 1 ]();
		await waitFor( () => expect( $.fn.unblock ).toHaveBeenCalled() );
	} );

	it( 'should guard the button from `update_checkout` onwards, before WooCommerce has refreshed', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );
		$.fn.block.mockClear();

		// WooCommerce fires this before its own `update_order_review` request.
		// Our container sits outside what core blocks, so the old button is
		// still tappable and `cachedCartData` is the pre-change snapshot.
		$( document.body ).trigger( 'update_checkout' );
		const request = orderReviewRequest();
		request.send();

		expect( $.fn.block ).toHaveBeenCalled();

		const clickEventResolveMock = jest.fn();
		const clickEventRejectMock = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: clickEventResolveMock,
			reject: clickEventRejectMock,
			expressPaymentType: 'google_pay',
		} );
		expect( clickEventRejectMock ).toHaveBeenCalledTimes( 1 );
		expect( clickEventResolveMock ).not.toHaveBeenCalled();

		// Core finished: our refresh runs and the button opens again.
		$( document.body ).trigger( 'updated_checkout' );
		request.succeed();
		await waitFor( () =>
			expect( stripeInstance.elements ).toHaveBeenCalledTimes( 2 )
		);
		await act( async () => {
			await Promise.resolve();
		} );

		const postRefreshResolveMock = jest.fn();
		const postRefreshRejectMock = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: postRefreshResolveMock,
			reject: postRefreshRejectMock,
			expressPaymentType: 'google_pay',
		} );
		expect( postRefreshResolveMock ).toHaveBeenCalledWith(
			expect.objectContaining( { shippingAddressRequired: true } )
		);
		expect( postRefreshRejectMock ).not.toHaveBeenCalled();
	} );

	it( 'should keep the button guarded when `update_checkout` fires again while a refresh is in flight', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );

		const releases = [];
		apiFetch.mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					releases.push( () =>
						resolve( {
							json: () => Promise.resolve( cartWithItemsMock ),
							headers: new Map(),
						} )
					);
				} )
		);

		// Core's first refresh completes and our refresh A starts.
		$( document.body ).trigger( 'update_checkout' );
		const firstRequest = orderReviewRequest();
		firstRequest.send();
		$( document.body ).trigger( 'updated_checkout' );
		firstRequest.succeed();
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 2 ) );
		$.fn.unblock.mockClear();

		// Core starts a second refresh while A is still waiting on the cart.
		$( document.body ).trigger( 'update_checkout' );
		const secondRequest = orderReviewRequest();
		secondRequest.send();

		// A settles, but core's second request is still pending: the button
		// must stay covered and clicks must still be rejected.
		releases[ 0 ]();
		await act( async () => {
			await Promise.resolve();
		} );
		expect( $.fn.unblock ).not.toHaveBeenCalled();

		const midRefreshResolveMock = jest.fn();
		const midRefreshRejectMock = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: midRefreshResolveMock,
			reject: midRefreshRejectMock,
			expressPaymentType: 'google_pay',
		} );
		expect( midRefreshRejectMock ).toHaveBeenCalledTimes( 1 );
		expect( midRefreshResolveMock ).not.toHaveBeenCalled();

		// Core finishes the second refresh: our refresh B releases the button.
		$( document.body ).trigger( 'updated_checkout' );
		secondRequest.succeed();
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 3 ) );
		releases[ 1 ]();
		await waitFor( () => expect( $.fn.unblock ).toHaveBeenCalled() );
	} );

	it( "should lift the guard when WooCommerce's order-review request fails", async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );

		$( document.body ).trigger( 'update_checkout' );
		const request = orderReviewRequest();
		request.send();
		$.fn.unblock.mockClear();

		// Core's `update_order_review` has only a `success` callback, so a 500
		// means `updated_checkout` never fires and nothing else lifts the overlay.
		request.fail();

		await waitFor( () =>
			expect( stripeInstance.elements ).toHaveBeenCalledTimes( 2 )
		);
		await act( async () => {
			await Promise.resolve();
		} );
		expect( $.fn.unblock ).toHaveBeenCalled();

		const resolveMock = jest.fn();
		const rejectMock = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: resolveMock,
			reject: rejectMock,
			expressPaymentType: 'google_pay',
		} );
		expect( resolveMock ).toHaveBeenCalledWith(
			expect.objectContaining( { shippingAddressRequired: true } )
		);
		expect( rejectMock ).not.toHaveBeenCalled();
	} );

	it( 'should keep the guard when WooCommerce aborts the order-review request', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );

		const aborted = orderReviewRequest();
		aborted.send();
		$( document.body ).trigger( 'update_checkout' );
		$.fn.unblock.mockClear();
		apiFetch.mockClear();

		// Core aborts the in-flight request when a newer `update_checkout` starts.
		// That newer one guards the button itself, so recovering here would open
		// the button over a request that is still running.
		aborted.abort();

		await act( async () => {
			await Promise.resolve();
		} );
		expect( apiFetch ).not.toHaveBeenCalled();
		expect( $.fn.unblock ).not.toHaveBeenCalled();

		const resolveMock = jest.fn();
		const rejectMock = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: resolveMock,
			reject: rejectMock,
			expressPaymentType: 'google_pay',
		} );
		expect( rejectMock ).toHaveBeenCalledTimes( 1 );
		expect( resolveMock ).not.toHaveBeenCalled();

		// Core sends the replacement in the same breath, and that one releases it.
		const replacement = orderReviewRequest();
		replacement.send();
		$( document.body ).trigger( 'updated_checkout' );
		replacement.succeed();

		await waitFor( () => expect( $.fn.unblock ).toHaveBeenCalled() );
	} );

	// Core debounces `update_checkout` by 5 ms, so a request settling inside that
	// window leaves nothing in flight while our refresh resolves on a stale cart.
	it( 'should keep the guard when a failed request settles inside the debounce window', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );

		const request = orderReviewRequest();
		request.send();

		$( document.body ).trigger( 'update_checkout' );
		$.fn.unblock.mockClear();
		request.fail();

		await act( async () => {
			await Promise.resolve();
			await Promise.resolve();
		} );
		expect( $.fn.unblock ).not.toHaveBeenCalled();

		const queued = orderReviewRequest();
		queued.send();
		$( document.body ).trigger( 'updated_checkout' );
		queued.succeed();

		await waitFor( () => expect( $.fn.unblock ).toHaveBeenCalled() );
	} );

	it( 'should keep the guard when a successful request settles inside the debounce window', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );

		const request = orderReviewRequest();
		request.send();

		$( document.body ).trigger( 'update_checkout' );
		$.fn.unblock.mockClear();

		// Core fires `updated_checkout` from `success` regardless of the queued update.
		$( document.body ).trigger( 'updated_checkout' );
		request.succeed();

		await act( async () => {
			await Promise.resolve();
			await Promise.resolve();
		} );
		expect( $.fn.unblock ).not.toHaveBeenCalled();

		const queued = orderReviewRequest();
		queued.send();
		$( document.body ).trigger( 'updated_checkout' );
		queued.succeed();

		await waitFor( () => expect( $.fn.unblock ).toHaveBeenCalled() );
	} );

	it( 'should ignore a third-party request carrying the endpoint name', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );

		const request = orderReviewRequest();
		request.send();

		$( document.body ).trigger( 'update_checkout' );
		$.fn.unblock.mockClear();
		request.fail();

		// A looser match would read this as core's and declare the cycle over.
		const impostor = orderReviewRequest(
			'/wp-json/my-plugin/update_order_review'
		);
		impostor.send();
		impostor.fail();

		await act( async () => {
			await Promise.resolve();
			await Promise.resolve();
		} );
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
		expect( $.fn.unblock ).not.toHaveBeenCalled();

		// Only core's own request releases it.
		const queued = orderReviewRequest();
		queued.send();
		$( document.body ).trigger( 'updated_checkout' );
		queued.succeed();

		await waitFor( () => expect( $.fn.unblock ).toHaveBeenCalled() );
	} );

	it( 'should follow the cycle when a filtered endpoint takes its first query parameter', async () => {
		global.wc_checkout_params.wc_ajax_url = '/wc-ajax/%%endpoint%%';

		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );

		$( document.body ).trigger( 'update_checkout' );
		$.fn.unblock.mockClear();

		const request = orderReviewRequest(
			'/wc-ajax/update_order_review?lang=fr'
		);
		request.send();
		$( document.body ).trigger( 'updated_checkout' );
		request.succeed();

		await waitFor( () => expect( $.fn.unblock ).toHaveBeenCalled() );
	} );

	it( 'should follow the cycle once jQuery has expanded a protocol-relative endpoint', async () => {
		global.wc_checkout_params.wc_ajax_url =
			'//localhost/?wc-ajax=%%endpoint%%';

		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );

		$( document.body ).trigger( 'update_checkout' );
		$.fn.unblock.mockClear();

		const request = orderReviewRequest(
			'http://localhost/?wc-ajax=update_order_review'
		);
		request.send();
		$( document.body ).trigger( 'updated_checkout' );
		request.succeed();

		await waitFor( () => expect( $.fn.unblock ).toHaveBeenCalled() );
	} );

	it( 'should initialize Elements with setupFutureUsage when the current cart contains a subscription', async () => {
		global.wcpayExpressCheckoutParams.has_subscription = false;

		const cartWithSubscription = {
			...cartWithItemsMock,
			extensions: {
				subscriptions: [
					{
						billing_period: 'month',
						billing_interval: 1,
						totals: { total_price: '2399' },
					},
				],
			},
		};
		apiFetch.mockImplementation( async () =>
			Promise.resolve( {
				json: () => Promise.resolve( cartWithSubscription ),
				headers: new Map(),
			} )
		);

		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );

		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );

		expect( stripeInstance.elements ).toHaveBeenCalledWith(
			expect.objectContaining( {
				setupFutureUsage: 'off_session',
			} )
		);
	} );
} );
