/** @format */

/**
 * External dependencies
 */
import { setLocaleData } from '@wordpress/i18n';

// Set up `wp.*` aliases.  Doing this because any tests importing wp stuff will
// likely run into this.
global.wp = {
	shortcode: {
		next() {},
		regexp: jest.fn().mockReturnValue( new RegExp() ),
	},
};

global.wc = {};

const wordPressPackages = [ 'element', 'date' ];

const wooCommercePackages = [
	'components',
	'csv',
	'currency',
	'date',
	'navigation',
	'number',
];

global.window.wcTracks = {
	recordEvent: () => null,
};

global.IS_REACT_ACT_ENVIRONMENT = true;

// Can be removed once a new version of @wordpress/jest-preset-default include it ( >= 10.1.0 )
global.window.matchMedia = () => ( {
	matches: false,
	addListener: () => {},
	addEventListener: () => {},
	removeListener: () => {},
	removeEventListener: () => {},
} );

global.window.ResizeObserver = jest.fn().mockImplementation( () => ( {
	observe: jest.fn(),
	unobserve: jest.fn(),
	disconnect: jest.fn(),
} ) );

wordPressPackages.forEach( ( lib ) => {
	Object.defineProperty( global.wp, lib, {
		get: () => require( `@wordpress/${ lib }` ),
	} );
} );

wooCommercePackages.forEach( ( lib ) => {
	Object.defineProperty( global.wc, lib, {
		get: () => require( `@woocommerce/${ lib }` ),
	} );
} );

global.wcSettings = {
	// adminUrl: 'https://vagrant.local/wp/wp-admin/',
	// locale: 'en-US',
	// currency: { code: 'USD', precision: 2, symbol: '$' },
	// date: {
	// 	dow: 0,
	// },
	orderStatuses: {
		pending: 'Pending payment',
		processing: 'Processing',
		'on-hold': 'On hold',
		completed: 'Completed',
		cancelled: 'Cancelled',
		refunded: 'Refunded',
		failed: 'Failed',
		paid: 'Paid',
	},
	locale: {
		siteLocale: 'en_US',
		userLocale: 'en_US',
		weekdaysShort: [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ],
	},
	// wcAdminSettings: {
	// 	woocommerce_actionable_order_statuses: [],
	// 	woocommerce_excluded_report_order_statuses: [],
	// },
	siteTitle: 'WooCommerce Payments Dev',
	countries: {
		US: 'United States of America',
		CA: 'Canada',
		UK: 'United Kingdom',
	},
	storePages: {
		checkout: {
			permalink: 'http://localhost/',
		},
	},
};

global.wpApiSettings = {
	root: 'https://site.com/wp-json/',
	nonce: 'random_wp_rest_nonce',
};

global.wcpaySettings = {
	accountLoans: {
		loans: [ 'flxln_123456|active' ],
	},
};

// const config = require( '../../config/development.json' );
// window.wcAdminFeatures = config && config.features ? config.features : {};

setLocaleData(
	{ '': { domain: 'woocommerce-payments', lang: 'en_US' } },
	'woocommerce-payments'
);

global.jQuery = jest.fn();

global.ResizeObserver = jest.fn().mockImplementation( () => ( {
	observe: jest.fn(),
	unobserve: jest.fn(),
	disconnect: jest.fn(),
} ) );

// Mock the tracks module to avoid the need to mock wcpaySettings in every test.
jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
	isEnabled: jest.fn(),
	events: {},
} ) );

function buildMockDefinition( id, label, currencies = [], overrides = {} ) {
	return {
		id,
		label,
		description: `Mock ${ label } Description`,
		settings_icon_url: `assets/images/icon-${ id }.svg`,
		currencies,
		stripe_key: `${ id }_payments`,
		allows_manual_capture: false,
		allows_pay_later: false,
		accepts_only_domestic_payment: false,
		...overrides,
	};
}

// This doesn't include all the payment methods, only the ones relevant for tests.
global.wooPaymentsPaymentMethodDefinitions = {
	card: buildMockDefinition( 'card', 'Credit / Debit Cards', [], {
		allows_manual_capture: true,
	} ),
	bancontact: buildMockDefinition( 'bancontact', 'Bancontact', [ 'EUR' ] ),
	ideal: buildMockDefinition( 'ideal', 'iDEAL', [ 'EUR' ] ),
	eps: buildMockDefinition( 'eps', 'EPS', [ 'EUR' ] ),
	giropay: buildMockDefinition( 'giropay', 'Giropay', [ 'EUR' ] ),
	sofort: buildMockDefinition( 'sofort', 'Sofort', [ 'EUR' ] ),
	sepa_debit: buildMockDefinition( 'sepa_debit', 'SEPA Direct Debit', [
		'EUR',
	] ),
	p24: buildMockDefinition( 'p24', 'Przelewy24 (P24)', [ 'EUR', 'PLN' ] ),
	au_becs_debit: buildMockDefinition( 'au_becs_debit', 'BECS Direct Debit', [
		'AUD',
	] ),
	affirm: buildMockDefinition( 'affirm', 'Affirm', [ 'USD', 'CAD' ], {
		allows_pay_later: true,
		accepts_only_domestic_payment: true,
	} ),
	afterpay_clearpay: buildMockDefinition(
		'afterpay_clearpay',
		'Afterpay',
		[ 'USD', 'AUD', 'CAD', 'NZD', 'GBP' ],
		{ allows_pay_later: true, accepts_only_domestic_payment: true }
	),
	klarna: buildMockDefinition(
		'klarna',
		'Klarna',
		[ 'EUR', 'GBP', 'USD', 'DKK', 'NOK', 'SEK' ],
		{
			allows_pay_later: true,
			accepts_only_domestic_payment: true,
		}
	),
	alipay: buildMockDefinition( 'alipay', 'Alipay', [ 'USD' ] ),
	google_pay: buildMockDefinition( 'google_pay', 'Google Pay' ),
	apple_pay: buildMockDefinition( 'apple_pay', 'Apple Pay' ),
};
