/**
 * External dependencies
 */
import { act, render, screen, waitFor } from '@testing-library/react';
import $ from 'jquery';
import { recordUserEvent } from 'tracks';
import apiFetch from '@wordpress/api-fetch';
import {
	cartWithItemsMock,
	cartWithItemsAndCouponMock,
} from '../../__fixtures__/cart';

jest.mock( 'tracks', () => ( {
	recordUserEvent: jest.fn(),
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
		// removing all the registered event handlers so they don't leak between tests.
		global.$( document.body ).off();
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

	it( 'should work as soon as a newer refresh succeeds, without waiting out a hung one', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );

		// Refresh A hangs: its Store API promise never settles.
		apiFetch.mockImplementation( () => new Promise( () => {} ) );
		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 2 ) );

		const rejectDuringHungRefresh = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: jest.fn(),
			reject: rejectDuringHungRefresh,
			expressPaymentType: 'google_pay',
		} );
		expect( rejectDuringHungRefresh ).toHaveBeenCalledTimes( 1 );

		// Refresh B starts and succeeds while A is still pending.
		apiFetch.mockImplementation( async () =>
			Promise.resolve( {
				json: () => Promise.resolve( cartWithItemsMock ),
				headers: new Map(),
			} )
		);
		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 3 ) );

		const resolveAfterNewerRefresh = jest.fn();
		const rejectAfterNewerRefresh = jest.fn();
		await waitFor( () => {
			resolveAfterNewerRefresh.mockClear();
			rejectAfterNewerRefresh.mockClear();
			stripeElementMock.__getRegisteredEvent( 'click' )( {
				resolve: resolveAfterNewerRefresh,
				reject: rejectAfterNewerRefresh,
				expressPaymentType: 'google_pay',
			} );
			expect( resolveAfterNewerRefresh ).toHaveBeenCalledWith(
				expect.objectContaining( { shippingAddressRequired: true } )
			);
		} );
		expect( rejectAfterNewerRefresh ).not.toHaveBeenCalled();
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

	it( 'should reject the click event when the cart data could not be fetched', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );

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
