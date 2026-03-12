/**
 * Internal dependencies
 */
import { WCPayAsyncPriceRenderer } from '../index';

describe( 'WCPayAsyncPriceRenderer', () => {
	let renderer;
	// Save the global fetch reference (MSW-intercepted) so we can restore it
	// after tests that override global.fetch with jest.fn(). Deleting
	// global.fetch breaks MSW's server.close() cleanup.
	let savedFetch;

	const mockConfig = {
		default_currency: 'USD',
		selected_currency: 'EUR',
		charm_only_products: true,
		currencies: {
			USD: {
				code: 'USD',
				symbol: '$',
				rate: 1,
				decimals: 2,
				decimal_sep: '.',
				thousand_sep: ',',
				symbol_pos: 'left',
				rounding: 0,
				charm: 0,
			},
			EUR: {
				code: 'EUR',
				symbol: '\u20ac',
				rate: 0.85,
				decimals: 2,
				decimal_sep: ',',
				thousand_sep: '.',
				symbol_pos: 'right_space',
				rounding: 1,
				charm: -0.01,
			},
			JPY: {
				code: 'JPY',
				symbol: '\u00a5',
				rate: 110.5,
				decimals: 0,
				decimal_sep: '.',
				thousand_sep: ',',
				symbol_pos: 'left',
				rounding: 100,
				charm: 0,
			},
		},
	};

	beforeEach( () => {
		savedFetch = global.fetch;
		renderer = new WCPayAsyncPriceRenderer();
		renderer.config = mockConfig;
	} );

	describe( 'init', () => {
		const apiUrl =
			'https://example.com/wp-json/wc/v3/payments/multi-currency/public/config';

		beforeEach( () => {
			document.body.textContent = '';
			global.wcpayAsyncPriceConfig = { apiUrl };
			global.jQuery = jest.fn( () => ( {
				on: jest.fn().mockReturnThis(),
				off: jest.fn(),
			} ) );
			sessionStorage.clear();
		} );

		afterEach( () => {
			renderer.destroy();
			delete global.jQuery;
			delete global.wcpayAsyncPriceConfig;
			global.fetch = savedFetch;
		} );

		it( 'fetches config and converts prices on success', async () => {
			global.fetch = jest.fn().mockResolvedValue( {
				ok: true,
				json: () => Promise.resolve( mockConfig ),
			} );

			const span = document.createElement( 'span' );
			span.setAttribute( 'data-wcpay-price', '10' );
			const skeleton = document.createElement( 'bdi' );
			skeleton.className = 'wcpay-price-skeleton';
			span.appendChild( skeleton );
			document.body.appendChild( span );

			await renderer.init();

			expect( renderer.config ).toEqual( mockConfig );
			expect( span.classList.contains( 'wcpay-price-converted' ) ).toBe(
				true
			);
		} );

		it( 'shows error state on fetch failure', async () => {
			global.fetch = jest
				.fn()
				.mockRejectedValue( new Error( 'Network error' ) );

			const wrapper = document.createElement( 'span' );
			wrapper.setAttribute( 'data-wcpay-price', '10.00' );
			const skeleton = document.createElement( 'bdi' );
			skeleton.className = 'wcpay-price-skeleton';
			wrapper.appendChild( skeleton );
			document.body.appendChild( wrapper );

			await renderer.init();

			expect(
				document.querySelector( '.wcpay-price-error' )
			).not.toBeNull();
		} );

		it( 'only initializes once', async () => {
			global.fetch = jest.fn().mockResolvedValue( {
				ok: true,
				json: () => Promise.resolve( mockConfig ),
			} );

			await renderer.init();
			await renderer.init();

			expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'shows error state when config fetch times out', async () => {
			jest.useFakeTimers();
			global.fetch = jest
				.fn()
				.mockImplementation( () => new Promise( () => {} ) ); // never resolves

			const wrapper = document.createElement( 'span' );
			wrapper.setAttribute( 'data-wcpay-price', '10.00' );
			const skeleton = document.createElement( 'bdi' );
			skeleton.className = 'wcpay-price-skeleton';
			wrapper.appendChild( skeleton );
			document.body.appendChild( wrapper );

			const initPromise = renderer.init();
			jest.advanceTimersByTime( 10000 );
			await initPromise;

			expect(
				document.querySelector( '.wcpay-price-error' )
			).not.toBeNull();

			jest.useRealTimers();
		} );
	} );

	describe( 'convertPrice', () => {
		it( 'converts a product price with rate, rounding, and charm', () => {
			// 10.00 USD * 0.85 = 8.50 EUR
			// Rounding 1.00: ceil(8.50 / 1) * 1 = 9
			// Charm: 9 + (-0.01) = 8.99
			const result = renderer.convertPrice( '10.00', 'product' );
			expect( result ).toBe( '8,99' );
		} );

		it( 'converts a shipping price with rounding but no charm (charm_only_products=true)', () => {
			// 10.00 USD * 0.85 = 8.50 EUR
			// Rounding 1.00: ceil(8.50 / 1) * 1 = 9
			// No charm for shipping when charm_only_products is true
			const result = renderer.convertPrice( '10.00', 'shipping' );
			expect( result ).toBe( '9,00' );
		} );

		it( 'applies charm to shipping when charm_only_products is false', () => {
			renderer.config = {
				...mockConfig,
				charm_only_products: false,
			};

			// 10.00 USD * 0.85 = 8.50 EUR
			// Rounding 1.00: ceil(8.50 / 1) * 1 = 9
			// Charm: 9 + (-0.01) = 8.99
			const result = renderer.convertPrice( '10.00', 'shipping' );
			expect( result ).toBe( '8,99' );
		} );

		it( 'converts a tax amount without rounding', () => {
			// 1.50 USD * 0.85 = 1.275 EUR
			// Tax: round to 2 decimals = 1.28
			const result = renderer.convertPrice( '1.50', 'tax' );
			expect( result ).toBe( '1,28' );
		} );

		it( 'converts a coupon amount without rounding', () => {
			// 5.00 USD * 0.85 = 4.25 EUR
			// Coupon: round to 2 decimals = 4.25
			const result = renderer.convertPrice( '5.00', 'coupon' );
			expect( result ).toBe( '4,25' );
		} );

		it( 'converts an exchange_rate amount without rounding', () => {
			// 5.00 USD * 0.85 = 4.25 EUR
			// exchange_rate: round to 2 decimals = 4.25
			const result = renderer.convertPrice( '5.00', 'exchange_rate' );
			expect( result ).toBe( '4,25' );
		} );

		it( 'returns same currency price when selected equals default', () => {
			renderer.config = {
				...mockConfig,
				selected_currency: 'USD',
			};

			const result = renderer.convertPrice( '10.00', 'product' );
			expect( result ).toBe( '10.00' );
		} );

		it( 'never returns negative prices', () => {
			// Small price with large negative charm would go below zero.
			renderer.config = {
				...mockConfig,
				currencies: {
					...mockConfig.currencies,
					EUR: {
						...mockConfig.currencies.EUR,
						charm: -100,
					},
				},
			};

			const result = renderer.convertPrice( '0.50', 'product' );
			expect( result ).toBe( '0,00' );
		} );

		it( 'caches converted prices', () => {
			const result1 = renderer.convertPrice( '10.00', 'product' );
			const result2 = renderer.convertPrice( '10.00', 'product' );
			expect( result1 ).toBe( result2 );
			expect( renderer.cache.size ).toBe( 1 );
		} );

		it( 'evicts oldest cache entry when cache is full', () => {
			// Fill cache to MAX_CACHE_SIZE (500).
			for ( let i = 0; i < 500; i++ ) {
				renderer.convertPrice( String( i + 0.01 ), 'product' );
			}
			expect( renderer.cache.size ).toBe( 500 );

			// Add one more entry, which should evict the first.
			renderer.convertPrice( '999.99', 'product' );
			expect( renderer.cache.size ).toBe( 500 );
			expect( renderer.cache.has( '0.01_product' ) ).toBe( false );
		} );
	} );

	describe( 'formatPrice', () => {
		it( 'formats number with correct decimals and separators', () => {
			const Decimal = require( 'decimal.js-light' );
			const result = renderer.formatPrice(
				new Decimal( '10.50' ),
				mockConfig.currencies.USD
			);
			expect( result ).toBe( '10.50' );
		} );

		it( 'uses currency decimal separator', () => {
			const Decimal = require( 'decimal.js-light' );
			const result = renderer.formatPrice(
				new Decimal( '8.99' ),
				mockConfig.currencies.EUR
			);
			expect( result ).toBe( '8,99' );
		} );

		it( 'formats zero-decimal currencies correctly', () => {
			const Decimal = require( 'decimal.js-light' );
			const result = renderer.formatPrice(
				new Decimal( '1100' ),
				mockConfig.currencies.JPY
			);
			expect( result ).toBe( '1,100' );
		} );

		it( 'adds thousand separators correctly', () => {
			const Decimal = require( 'decimal.js-light' );
			const result = renderer.formatPrice(
				new Decimal( '1234567.89' ),
				mockConfig.currencies.USD
			);
			expect( result ).toBe( '1,234,567.89' );
		} );
	} );

	describe( 'convertAllPrices', () => {
		beforeEach( () => {
			document.body.textContent = '';
		} );

		it( 'converts skeleton elements to formatted prices', () => {
			// Build test DOM matching SSR markup: wrapper IS woocommerce-Price-amount.
			const span = document.createElement( 'span' );
			span.className =
				'woocommerce-Price-amount amount wcpay-async-price';
			span.setAttribute( 'data-wcpay-price', '10' );
			span.setAttribute( 'data-wcpay-price-type', 'product' );
			const skeleton = document.createElement( 'bdi' );
			skeleton.className = 'wcpay-price-skeleton';
			span.appendChild( skeleton );
			document.body.appendChild( span );

			renderer.convertAllPrices();

			const el = document.querySelector( '.wcpay-async-price' );
			expect( el.classList.contains( 'wcpay-price-converted' ) ).toBe(
				true
			);
			expect( el.querySelector( '.wcpay-price-skeleton' ) ).toBeNull();
			// The <bdi> is appended directly — no extra wrapper.
			const bdi = el.querySelector( 'bdi' );
			expect( bdi ).not.toBeNull();
			expect(
				bdi.querySelector( '.woocommerce-Price-currencySymbol' )
			).not.toBeNull();
			expect(
				bdi.querySelector( '.woocommerce-Price-currencySymbol' )
					.textContent
			).toBe( '\u20ac' ); // EUR is selected_currency in mockConfig
		} );

		it( 'removes SSR placeholder when converting', () => {
			const span = document.createElement( 'span' );
			span.className =
				'woocommerce-Price-amount amount wcpay-async-price';
			span.setAttribute( 'data-wcpay-price', '10' );
			span.setAttribute( 'data-wcpay-price-type', 'product' );
			const skeleton = document.createElement( 'bdi' );
			skeleton.className = 'wcpay-price-skeleton';
			span.appendChild( skeleton );
			const placeholder = document.createElement( 'span' );
			placeholder.className =
				'screen-reader-text wcpay-price-placeholder';
			placeholder.textContent = '$10.00';
			span.appendChild( placeholder );
			document.body.appendChild( span );

			renderer.convertAllPrices();

			expect(
				span.querySelector( '.wcpay-price-placeholder' )
			).toBeNull();
		} );

		it( 'skips already converted elements', () => {
			const span = document.createElement( 'span' );
			span.className =
				'woocommerce-Price-amount amount wcpay-async-price wcpay-price-converted';
			span.setAttribute( 'data-wcpay-price', '10' );
			span.setAttribute( 'data-wcpay-price-type', 'product' );
			const bdi = document.createElement( 'bdi' );
			bdi.textContent = '8,99\u00a0\u20ac';
			span.appendChild( bdi );
			document.body.appendChild( span );

			renderer.convertAllPrices();

			// Should still have only one <bdi> (not append another).
			const bdis = span.querySelectorAll( 'bdi' );
			expect( bdis.length ).toBe( 1 );
		} );
	} );

	describe( 'showErrorState', () => {
		const apiUrl =
			'https://example.com/wp-json/wc/v3/payments/multi-currency/public/config';

		const mockDefaultCurrency = {
			code: 'USD',
			symbol: '$',
			rate: 1,
			decimals: 2,
			decimal_sep: '.',
			thousand_sep: ',',
			symbol_pos: 'left',
			rounding: 0,
			charm: 0,
		};

		const createPriceWrapper = ( price = '10.00' ) => {
			const wrapper = document.createElement( 'span' );
			wrapper.setAttribute( 'data-wcpay-price', price );
			const skeleton = document.createElement( 'bdi' );
			skeleton.className = 'wcpay-price-skeleton';
			wrapper.appendChild( skeleton );
			document.body.appendChild( wrapper );
			return wrapper;
		};

		beforeEach( () => {
			document.body.textContent = '';
		} );

		afterEach( () => {
			delete global.wcpayAsyncPriceConfig;
		} );

		it( 'formats price with default currency when available', () => {
			global.wcpayAsyncPriceConfig = {
				apiUrl,
				defaultCurrency: mockDefaultCurrency,
			};
			const wrapper = createPriceWrapper( '10.00' );

			renderer.showErrorState();

			expect(
				wrapper.classList.contains( 'wcpay-price-converted' )
			).toBe( true );
			expect(
				wrapper.querySelector( '.wcpay-price-skeleton' )
			).toBeNull();
			// <bdi> is appended directly into wrapper.
			const bdi = wrapper.querySelector( 'bdi' );
			expect( bdi ).not.toBeNull();
			expect( bdi.textContent ).toBe( '$10.00' );
			expect(
				bdi.querySelector( '.woocommerce-Price-currencySymbol' )
			).not.toBeNull();
			expect(
				bdi.querySelector( '.woocommerce-Price-currencySymbol' )
					.textContent
			).toBe( '$' );
		} );

		it( 'removes SSR placeholder when formatting with default currency', () => {
			global.wcpayAsyncPriceConfig = {
				apiUrl,
				defaultCurrency: mockDefaultCurrency,
			};
			const wrapper = createPriceWrapper( '10.00' );
			const placeholder = document.createElement( 'span' );
			placeholder.className =
				'screen-reader-text wcpay-price-placeholder';
			placeholder.textContent = '$10.00';
			wrapper.appendChild( placeholder );

			renderer.showErrorState();

			expect(
				wrapper.querySelector( '.wcpay-price-placeholder' )
			).toBeNull();
		} );

		it( 'keeps SSR placeholder on em-dash fallback so screen readers get the price', () => {
			global.wcpayAsyncPriceConfig = { apiUrl };
			const wrapper = createPriceWrapper( '10.00' );
			const placeholder = document.createElement( 'span' );
			placeholder.className =
				'screen-reader-text wcpay-price-placeholder';
			placeholder.textContent = '$10.00';
			wrapper.appendChild( placeholder );

			renderer.showErrorState();

			expect(
				wrapper.querySelector( '.wcpay-price-placeholder' )
			).not.toBeNull();
		} );

		it( 'shows em dash when no default currency is available', () => {
			global.wcpayAsyncPriceConfig = { apiUrl };
			createPriceWrapper( '10.00' );

			renderer.showErrorState();

			const el = document.querySelector( '.wcpay-price-error' );
			expect( el ).not.toBeNull();
			expect( el.textContent ).toBe( '\u2014' );
		} );
	} );

	describe( 'buildPriceBdi', () => {
		it( 'creates bdi with symbol class for left position', () => {
			const bdi = renderer.buildPriceBdi(
				'10.50',
				mockConfig.currencies.USD
			);
			expect( bdi.tagName ).toBe( 'BDI' );
			const symbol = bdi.querySelector(
				'.woocommerce-Price-currencySymbol'
			);
			expect( symbol ).not.toBeNull();
			expect( symbol.textContent ).toBe( '$' );
			expect( bdi.textContent ).toBe( '$10.50' );
		} );

		it( 'creates correct markup for right_space position', () => {
			const bdi = renderer.buildPriceBdi(
				'8,99',
				mockConfig.currencies.EUR
			);
			const symbol = bdi.querySelector(
				'.woocommerce-Price-currencySymbol'
			);
			expect( symbol.textContent ).toBe( '\u20ac' );
			expect( bdi.textContent ).toBe( '8,99\u00a0\u20ac' );
		} );

		it( 'creates correct markup for left_space position', () => {
			const currency = {
				...mockConfig.currencies.USD,
				symbol_pos: 'left_space',
			};
			const bdi = renderer.buildPriceBdi( '10.50', currency );
			const symbol = bdi.querySelector(
				'.woocommerce-Price-currencySymbol'
			);
			expect( symbol.textContent ).toBe( '$' );
			expect( bdi.textContent ).toBe( '$\u00a010.50' );
		} );

		it( 'creates correct markup for right position', () => {
			const currency = {
				...mockConfig.currencies.USD,
				symbol_pos: 'right',
			};
			const bdi = renderer.buildPriceBdi( '10.50', currency );
			const symbol = bdi.querySelector(
				'.woocommerce-Price-currencySymbol'
			);
			expect( symbol.textContent ).toBe( '$' );
			expect( bdi.textContent ).toBe( '10.50$' );
		} );
	} );

	describe( 'listenToWooCommerceEvents', () => {
		afterEach( () => {
			delete global.jQuery;
		} );

		it( 'calls convertAllPrices when a bound WooCommerce event fires', () => {
			let boundHandler;
			global.jQuery = jest.fn( () => ( {
				on: jest.fn().mockImplementation( ( _events, handler ) => {
					boundHandler = handler;
					return { on: jest.fn() };
				} ),
				off: jest.fn(),
			} ) );

			renderer.listenToWooCommerceEvents();

			const convertSpy = jest
				.spyOn( renderer, 'convertAllPrices' )
				.mockImplementation( () => {} );

			// Simulate WooCommerce firing one of the bound events.
			boundHandler();

			expect( convertSpy ).toHaveBeenCalledTimes( 1 );
			convertSpy.mockRestore();
		} );
	} );

	describe( 'destroy', () => {
		it( 'disconnects observer and clears cache', () => {
			const disconnectFn = jest.fn();
			renderer.observer = { disconnect: disconnectFn };
			renderer.cache.set( 'test', 'value' );

			renderer.destroy();

			expect( disconnectFn ).toHaveBeenCalledTimes( 1 );
			expect( renderer.observer ).toBeNull();
			expect( renderer.cache.size ).toBe( 0 );
		} );

		it( 'removes jQuery event listeners', () => {
			const offFn = jest.fn().mockReturnThis();
			const onFn = jest.fn().mockReturnThis();
			global.jQuery = jest.fn( () => ( {
				on: onFn,
				off: offFn,
			} ) );

			renderer.listenToWooCommerceEvents();
			const handler = renderer.wcEventHandler;

			renderer.destroy();

			expect( offFn ).toHaveBeenCalledWith(
				'updated_cart_totals updated_checkout updated_wc_div',
				handler
			);
			expect( renderer.wcEventHandler ).toBeNull();

			delete global.jQuery;
		} );
	} );

	describe( 'decodeCurrencySymbols', () => {
		it( 'decodes HTML entities in currency symbols', () => {
			const config = {
				...mockConfig,
				currencies: {
					EUR: { ...mockConfig.currencies.EUR, symbol: '&euro;' },
					GBP: { ...mockConfig.currencies.EUR, symbol: '&pound;' },
					JPY: { ...mockConfig.currencies.JPY, symbol: '&yen;' },
				},
			};

			renderer.decodeCurrencySymbols( config );

			expect( config.currencies.EUR.symbol ).toBe( '\u20ac' );
			expect( config.currencies.GBP.symbol ).toBe( '\u00a3' );
			expect( config.currencies.JPY.symbol ).toBe( '\u00a5' );
		} );

		it( 'leaves plain symbols unchanged', () => {
			const config = {
				...mockConfig,
				currencies: {
					USD: { ...mockConfig.currencies.USD },
				},
			};

			renderer.decodeCurrencySymbols( config );

			expect( config.currencies.USD.symbol ).toBe( '$' );
		} );

		it( 'handles missing currencies gracefully', () => {
			const config = {};
			expect( () =>
				renderer.decodeCurrencySymbols( config )
			).not.toThrow();
		} );
	} );

	describe( 'fetchConfig', () => {
		const apiUrl =
			'https://example.com/wp-json/wc/v3/payments/multi-currency/public/config';

		beforeEach( () => {
			global.wcpayAsyncPriceConfig = { apiUrl };
			sessionStorage.clear();
		} );

		afterEach( () => {
			delete global.wcpayAsyncPriceConfig;
			global.fetch = savedFetch;
			sessionStorage.clear();
		} );

		it( 'fetches config from the API URL', async () => {
			global.fetch = jest.fn().mockResolvedValue( {
				ok: true,
				json: () => Promise.resolve( mockConfig ),
			} );

			const config = await renderer.fetchConfig();
			expect( config ).toEqual( mockConfig );
			expect( global.fetch ).toHaveBeenCalledWith( apiUrl );
		} );

		it( 'decodes HTML entity symbols from API response', async () => {
			const apiResponse = {
				...mockConfig,
				currencies: {
					...mockConfig.currencies,
					EUR: {
						...mockConfig.currencies.EUR,
						symbol: '&euro;',
					},
				},
			};

			global.fetch = jest.fn().mockResolvedValue( {
				ok: true,
				json: () => Promise.resolve( apiResponse ),
			} );

			const config = await renderer.fetchConfig();
			expect( config.currencies.EUR.symbol ).toBe( '\u20ac' );
		} );

		it( 'throws on non-ok response', async () => {
			global.fetch = jest.fn().mockResolvedValue( {
				ok: false,
				status: 500,
			} );

			await expect( renderer.fetchConfig() ).rejects.toThrow(
				'Config fetch failed: 500'
			);
		} );

		it( 'stores config in sessionStorage after fetch', async () => {
			global.fetch = jest.fn().mockResolvedValue( {
				ok: true,
				json: () => Promise.resolve( mockConfig ),
			} );

			await renderer.fetchConfig();

			const stored = sessionStorage.getItem( 'wcpay_mc_async_config' );
			expect( stored ).not.toBeNull();
			const parsed = JSON.parse( stored );
			expect( parsed.data ).toEqual( mockConfig );
			expect( parsed.timestamp ).toBeGreaterThan( 0 );
		} );

		it( 'returns cached config without fetching', async () => {
			const entry = {
				data: mockConfig,
				timestamp: Date.now(),
			};
			sessionStorage.setItem(
				'wcpay_mc_async_config',
				JSON.stringify( entry )
			);
			global.fetch = jest.fn();

			const config = await renderer.fetchConfig();

			expect( config ).toEqual( mockConfig );
			expect( global.fetch ).not.toHaveBeenCalled();
		} );

		it( 'fetches fresh config when cache is expired', async () => {
			const entry = {
				data: mockConfig,
				timestamp: Date.now() - 400000, // 6+ minutes ago.
			};
			sessionStorage.setItem(
				'wcpay_mc_async_config',
				JSON.stringify( entry )
			);
			global.fetch = jest.fn().mockResolvedValue( {
				ok: true,
				json: () => Promise.resolve( mockConfig ),
			} );

			await renderer.fetchConfig();

			expect( global.fetch ).toHaveBeenCalledWith( apiUrl );
		} );

		it( 'fetches fresh config when sessionStorage has invalid data', async () => {
			sessionStorage.setItem( 'wcpay_mc_async_config', 'not-json' );
			global.fetch = jest.fn().mockResolvedValue( {
				ok: true,
				json: () => Promise.resolve( mockConfig ),
			} );

			const config = await renderer.fetchConfig();

			expect( config ).toEqual( mockConfig );
			expect( global.fetch ).toHaveBeenCalled();
		} );
	} );

	describe( 'observeDynamicContent', () => {
		beforeEach( () => {
			document.body.textContent = '';
		} );

		it( 'debounces rapid DOM mutations into a single convertAllPrices call', async () => {
			jest.useFakeTimers();
			const convertSpy = jest
				.spyOn( renderer, 'convertAllPrices' )
				.mockImplementation( () => {} );

			renderer.observeDynamicContent();

			// Add three price elements in rapid succession.
			for ( let i = 0; i < 3; i++ ) {
				const el = document.createElement( 'span' );
				el.setAttribute( 'data-wcpay-price', String( i ) );
				document.body.appendChild( el );
			}

			// Debounce timer not yet elapsed — no conversion yet.
			expect( convertSpy ).not.toHaveBeenCalled();

			// advanceTimersByTimeAsync flushes microtasks (MutationObserver
			// callbacks) as well as the 50ms debounce timer.
			await jest.advanceTimersByTimeAsync( 50 );

			expect( convertSpy ).toHaveBeenCalledTimes( 1 );

			renderer.destroy();
			convertSpy.mockRestore();
			jest.useRealTimers();
		} );
	} );
} );
