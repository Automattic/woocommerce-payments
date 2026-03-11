/* global wcpayAsyncPriceConfig, jQuery */

/**
 * External dependencies
 */
import Decimal from 'decimal.js-light';

/**
 * Internal dependencies
 */
import './style.scss';

const TIMEOUT_MS = 10000;
const MAX_CACHE_SIZE = 500;
const SESSION_CACHE_KEY = 'wcpay_mc_async_config';
const SESSION_CACHE_TTL_MS = 300000; // 5 minutes, matches Cache-Control max-age.

type SymbolPosition = 'left' | 'left_space' | 'right' | 'right_space';

interface CurrencyConfig {
	code: string;
	symbol: string;
	rate: number;
	decimals: number;
	decimal_sep: string;
	thousand_sep: string;
	symbol_pos: SymbolPosition;
	rounding: number;
	charm: number;
}

interface PublicConfig {
	default_currency: string;
	selected_currency: string;
	charm_only_products: boolean;
	currencies: Record< string, CurrencyConfig >;
}

interface SessionCacheEntry {
	data: PublicConfig;
	timestamp: number;
}

type PriceType = 'product' | 'shipping' | 'tax' | 'coupon' | 'exchange_rate';

declare const wcpayAsyncPriceConfig: {
	apiUrl: string;
	defaultCurrency?: CurrencyConfig;
};

declare const jQuery: JQueryStatic | undefined;

interface JQueryStatic {
	( selector: unknown ): JQueryObject;
}

interface JQueryObject {
	on( events: string, handler: () => void ): JQueryObject;
	off( events: string, handler: () => void ): JQueryObject;
}

/**
 * Async price renderer for cache-optimized multi-currency mode.
 *
 * Fetches currency config from the public REST endpoint and converts
 * all skeleton-wrapped prices on the client side.
 */
class WCPayAsyncPriceRenderer {
	config: PublicConfig | null;
	cache: Map< string, string >;
	initialized: boolean;
	observer: MutationObserver | null;
	wcEventHandler: ( () => void ) | null;
	debounceTimer: ReturnType< typeof setTimeout > | null;

	constructor() {
		this.config = null;
		this.cache = new Map();
		this.initialized = false;
		this.observer = null;
		this.wcEventHandler = null;
		this.debounceTimer = null;
	}

	/**
	 * Initialize the renderer: fetch config, convert prices, observe DOM.
	 */
	async init(): Promise< void > {
		if ( this.initialized ) {
			return;
		}
		this.initialized = true;

		let timeoutId: ReturnType< typeof setTimeout >;
		try {
			const timeoutPromise = new Promise< never >( ( _, reject ) => {
				timeoutId = setTimeout(
					() => reject( new Error( 'Config fetch timeout' ) ),
					TIMEOUT_MS
				);
			} );

			this.config = await Promise.race( [
				this.fetchConfig(),
				timeoutPromise,
			] );
			clearTimeout( timeoutId! );

			this.convertAllPrices();
			this.observeDynamicContent();
			this.listenToWooCommerceEvents();
		} catch ( error ) {
			clearTimeout( timeoutId! );
			this.showErrorState();
		}
	}

	/**
	 * Fetch the public multi-currency config from the REST API.
	 *
	 * Uses sessionStorage to cache the response for 5 minutes, avoiding
	 * a network request on every page navigation within the same session.
	 */
	async fetchConfig(): Promise< PublicConfig > {
		const cached = this.getCachedConfig();
		if ( cached ) {
			return cached;
		}

		const response = await fetch( wcpayAsyncPriceConfig.apiUrl );
		if ( ! response.ok ) {
			throw new Error( `Config fetch failed: ${ response.status }` );
		}
		const config: PublicConfig = await response.json();
		this.decodeCurrencySymbols( config );
		this.cacheConfig( config );
		return config;
	}

	/**
	 * Retrieve cached config from sessionStorage if still valid.
	 */
	getCachedConfig(): PublicConfig | null {
		try {
			const raw = sessionStorage.getItem( SESSION_CACHE_KEY );
			if ( ! raw ) {
				return null;
			}
			const entry: SessionCacheEntry = JSON.parse( raw );
			if ( Date.now() - entry.timestamp > SESSION_CACHE_TTL_MS ) {
				sessionStorage.removeItem( SESSION_CACHE_KEY );
				return null;
			}
			this.decodeCurrencySymbols( entry.data );
			return entry.data;
		} catch {
			return null;
		}
	}

	/**
	 * Store config in sessionStorage with a timestamp.
	 */
	cacheConfig( config: PublicConfig ): void {
		try {
			const entry: SessionCacheEntry = {
				data: config,
				timestamp: Date.now(),
			};
			sessionStorage.setItem(
				SESSION_CACHE_KEY,
				JSON.stringify( entry )
			);
		} catch {
			// sessionStorage unavailable or full — silently skip.
		}
	}

	/**
	 * Decode HTML entities in currency symbols (e.g. &euro; -> EUR symbol).
	 *
	 * Uses a detached textarea element to safely convert HTML entities to
	 * their character equivalents. The textarea is never added to the DOM
	 * and .value always returns plain text, so this is XSS-safe.
	 */
	decodeCurrencySymbols( config: PublicConfig ): void {
		if ( ! config.currencies ) {
			return;
		}
		const textarea = document.createElement( 'textarea' );
		for ( const code of Object.keys( config.currencies ) ) {
			const currency = config.currencies[ code ];
			if ( currency.symbol ) {
				/*
				 * Safe: textarea.value always returns plain text, never HTML.
				 * The textarea is detached (never in DOM) and used solely to
				 * leverage the browser's built-in HTML entity decoder.
				 */
				textarea.innerHTML = currency.symbol; // eslint-disable-line no-unsanitized/property
				currency.symbol = textarea.value;
			}
		}
	}

	/**
	 * Convert a price value based on currency settings.
	 *
	 * This mirrors the PHP conversion in MultiCurrency::get_price() and
	 * MultiCurrency::get_adjusted_price(). Changes here must be kept in sync.
	 */
	convertPrice( price: number | string, type: PriceType | string ): string {
		const cacheKey = `${ price }_${ type }`;
		if ( this.cache.has( cacheKey ) ) {
			return this.cache.get( cacheKey )!;
		}

		const selectedCode = this.config!.selected_currency;
		const currency = this.config!.currencies[ selectedCode ];

		if ( ! currency || selectedCode === this.config!.default_currency ) {
			const formatted = this.formatPrice(
				new Decimal( price ),
				currency ||
					this.config!.currencies[ this.config!.default_currency ]
			);
			this.setCacheEntry( cacheKey, formatted );
			return formatted;
		}

		let converted = new Decimal( price ).times(
			new Decimal( currency.rate )
		);

		if ( type === 'product' || type === 'shipping' ) {
			// Product and shipping: apply rounding and charm pricing.
			const rounding = new Decimal( currency.rounding );

			if ( rounding.gt( 0 ) ) {
				converted = converted
					.div( rounding )
					.toDecimalPlaces( 0, Decimal.ROUND_CEIL )
					.times( rounding );
			} else {
				converted = converted.toDecimalPlaces(
					currency.decimals,
					Decimal.ROUND_HALF_UP
				);
			}

			// Apply charm pricing based on the PHP filter setting.
			const charmOnlyProducts =
				this.config!.charm_only_products !== false;
			const charmTypes: string[] = charmOnlyProducts
				? [ 'product' ]
				: [ 'product', 'shipping' ];

			if ( charmTypes.includes( type ) ) {
				const charm = new Decimal( currency.charm );
				converted = converted.plus( charm );
			}
		} else {
			// Tax, coupon, exchange_rate, and any unknown types: simple rounding.
			// Using simple rounding as the safe default prevents unintended
			// rounding rules or charm pricing from applying to unknown price types.
			converted = converted.toDecimalPlaces(
				currency.decimals,
				Decimal.ROUND_HALF_UP
			);
		}

		// Never return negative prices.
		if ( converted.lt( 0 ) ) {
			converted = new Decimal( 0 );
		}

		const formatted = this.formatPrice( converted, currency );
		this.setCacheEntry( cacheKey, formatted );
		return formatted;
	}

	/**
	 * Add an entry to the price cache with size limit.
	 */
	setCacheEntry( key: string, value: string ): void {
		if ( this.cache.size >= MAX_CACHE_SIZE ) {
			// Remove oldest entry.
			const firstKey = this.cache.keys().next().value;
			if ( firstKey !== undefined ) {
				this.cache.delete( firstKey );
			}
		}
		this.cache.set( key, value );
	}

	/**
	 * Format a price with the currency's formatting settings.
	 *
	 * This intentionally does not use the admin-side `formatCurrency` utility
	 * from `includes/multi-currency/client/utils/currency/`, which depends on
	 * `wcpaySettings` (admin-only global) and heavy packages (@woocommerce/currency,
	 * lodash). Both are unavailable on the storefront. The formatting logic here
	 * should produce equivalent output to `@woocommerce/currency`'s Currency class.
	 */
	formatPrice( price: Decimal, currency: CurrencyConfig ): string {
		const fixed = price.toFixed( currency.decimals );
		const parts = fixed.split( '.' );
		const integerPart = parts[ 0 ];
		const decimalPart = parts[ 1 ] || '';

		// Add thousand separators.
		const formattedInteger = integerPart.replace(
			/\B(?=(\d{3})+(?!\d))/g,
			currency.thousand_sep
		);

		let formattedNumber = formattedInteger;
		if ( currency.decimals > 0 ) {
			formattedNumber += currency.decimal_sep + decimalPart;
		}

		return formattedNumber;
	}

	/**
	 * Build a <bdi> element with the formatted price content.
	 *
	 * The caller is responsible for the outer
	 * <span class="woocommerce-Price-amount amount"> wrapper — the SSR markup
	 * already provides it, so JS only needs to replace the <bdi> contents.
	 *
	 * Produces:
	 *   <bdi><span class="woocommerce-Price-currencySymbol">€</span>22,00</bdi>
	 *
	 * The symbol and number are assembled from separate pieces (mirroring how
	 * PHP's wc_price() uses sprintf with the price_format pattern), so no
	 * string-slicing heuristics are needed.
	 */
	buildPriceBdi(
		formattedNumber: string,
		currency: CurrencyConfig
	): HTMLElement {
		const bdi = document.createElement( 'bdi' );
		const symbolSpan = document.createElement( 'span' );
		symbolSpan.className = 'woocommerce-Price-currencySymbol';
		symbolSpan.textContent = currency.symbol;

		switch ( currency.symbol_pos ) {
			case 'right':
				bdi.appendChild( document.createTextNode( formattedNumber ) );
				bdi.appendChild( symbolSpan );
				break;
			case 'right_space':
				bdi.appendChild( document.createTextNode( formattedNumber ) );
				bdi.appendChild( document.createTextNode( '\u00a0' ) );
				bdi.appendChild( symbolSpan );
				break;
			case 'left_space':
				bdi.appendChild( symbolSpan );
				bdi.appendChild( document.createTextNode( '\u00a0' ) );
				bdi.appendChild( document.createTextNode( formattedNumber ) );
				break;
			default:
				// 'left' or unknown: symbol precedes number with no space.
				bdi.appendChild( symbolSpan );
				bdi.appendChild( document.createTextNode( formattedNumber ) );
		}

		return bdi;
	}

	/**
	 * Find all skeleton price elements and convert them.
	 */
	convertAllPrices(): void {
		const elements = document.querySelectorAll(
			'[data-wcpay-price]:not(.wcpay-price-converted)'
		);

		// Determine the effective display currency (mirrors convertPrice() logic).
		const selectedCode = this.config!.selected_currency;
		const selectedCurrency = this.config!.currencies[ selectedCode ];
		const effectiveCurrency =
			! selectedCurrency || selectedCode === this.config!.default_currency
				? this.config!.currencies[ this.config!.default_currency ]
				: selectedCurrency;

		elements.forEach( ( el ) => {
			const price = el.getAttribute( 'data-wcpay-price' );
			const type =
				el.getAttribute( 'data-wcpay-price-type' ) || 'product';

			const converted = this.convertPrice( price!, type );

			// Replace skeleton <bdi> and remove SSR placeholder.
			el.querySelector( '.wcpay-price-skeleton' )?.remove();
			el.querySelector( '.wcpay-price-placeholder' )?.remove();

			el.appendChild(
				this.buildPriceBdi( converted, effectiveCurrency )
			);
			el.classList.add( 'wcpay-price-converted' );
		} );
	}

	/**
	 * Set up a MutationObserver to convert prices in dynamically added content.
	 */
	observeDynamicContent(): void {
		this.observer = new MutationObserver( ( mutations ) => {
			let hasNewPrices = false;

			for ( const mutation of mutations ) {
				for ( const node of mutation.addedNodes ) {
					if ( node.nodeType !== Node.ELEMENT_NODE ) {
						continue;
					}

					const el = node as Element;
					if (
						el.matches?.(
							'[data-wcpay-price]:not(.wcpay-price-converted)'
						) ||
						el.querySelector?.(
							'[data-wcpay-price]:not(.wcpay-price-converted)'
						)
					) {
						hasNewPrices = true;
						break;
					}
				}

				if ( hasNewPrices ) {
					break;
				}
			}

			if ( hasNewPrices ) {
				clearTimeout( this.debounceTimer! );
				this.debounceTimer = setTimeout(
					() => this.convertAllPrices(),
					50
				);
			}
		} );

		this.observer.observe( document.body, {
			childList: true,
			subtree: true,
		} );
	}

	/**
	 * Listen to WooCommerce AJAX events that may update price markup.
	 *
	 * These jQuery-triggered events fire when WooCommerce replaces HTML
	 * fragments after AJAX operations (e.g., updating cart, refreshing
	 * checkout). The MutationObserver handles generic DOM changes, but
	 * these events provide a reliable trigger for WC-specific updates
	 * where replaced HTML may contain new skeleton price elements.
	 */
	listenToWooCommerceEvents(): void {
		if ( typeof jQuery === 'undefined' ) {
			return;
		}

		this.wcEventHandler = () => this.convertAllPrices();

		const events = [
			'updated_cart_totals',
			'updated_checkout',
			'updated_wc_div',
		];

		events.forEach( ( event ) => {
			jQuery!( document.body ).on( event, this.wcEventHandler! );
		} );
	}

	/**
	 * Show error state on all skeleton elements when config fetch fails.
	 *
	 * If the default currency is available in wcpayAsyncPriceConfig, formats
	 * the raw default-currency price as a fallback instead of showing an em dash.
	 * The default currency data is store-wide (not per-user) so it is safe to
	 * include in the cached page via wp_localize_script.
	 */
	showErrorState(): void {
		const defaultCurrency = wcpayAsyncPriceConfig.defaultCurrency;
		const elements = document.querySelectorAll(
			'[data-wcpay-price]:not(.wcpay-price-converted)'
		);

		elements.forEach( ( el ) => {
			const skeleton = el.querySelector( '.wcpay-price-skeleton' );
			const rawPrice = el.getAttribute( 'data-wcpay-price' );

			if ( defaultCurrency && rawPrice !== null ) {
				try {
					const formatted = this.formatPrice(
						new Decimal( rawPrice ),
						defaultCurrency
					);
					skeleton?.remove();
					// Remove placeholder; the converted element replaces it.
					el.querySelector( '.wcpay-price-placeholder' )?.remove();
					el.appendChild(
						this.buildPriceBdi( formatted, defaultCurrency )
					);
					el.classList.add( 'wcpay-price-converted' );
					return;
				} catch ( e ) {
					// Fall through to em dash error state.
				}
			}

			// Em dash fallback: placeholder stays for screen readers.
			if ( skeleton ) {
				skeleton.classList.remove( 'wcpay-price-skeleton' );
				skeleton.classList.add( 'wcpay-price-error' );
				skeleton.textContent = '\u2014';
			}
		} );
	}

	/**
	 * Clean up the observer and event listeners.
	 */
	destroy(): void {
		if ( this.observer ) {
			this.observer.disconnect();
			this.observer = null;
		}

		if ( this.wcEventHandler && typeof jQuery !== 'undefined' ) {
			jQuery!( document.body ).off(
				'updated_cart_totals updated_checkout updated_wc_div',
				this.wcEventHandler
			);
			this.wcEventHandler = null;
		}

		clearTimeout( this.debounceTimer! );
		this.debounceTimer = null;
		this.cache.clear();
		this.initialized = false;
		this.config = null;
	}
}

// Export for testing.
export { WCPayAsyncPriceRenderer };

// Initialize when DOM is ready.
if ( typeof wcpayAsyncPriceConfig !== 'undefined' ) {
	const renderer = new WCPayAsyncPriceRenderer();

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => renderer.init() );
	} else {
		renderer.init();
	}
}
