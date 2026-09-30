/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentDetailsTimeline from '../';
import { useTimeline } from 'wcpay/data/timeline';

jest.mock( 'wcpay/data/timeline', () => ( {
	useTimeline: jest.fn(),
} ) );

jest.mock( '@woocommerce/components', () => {
	const { createElement } = jest.requireActual( '@wordpress/element' );

	return {
		Link: ( { href, children } ) =>
			createElement(
				'a',
				{
					href,
				},
				children
			),
		Timeline: ( { items, timezone } ) =>
			createElement(
				'div',
				{
					'data-testid': 'timeline',
					'data-timezone': timezone,
				},
				items.length
			),
	};
} );

describe( 'PaymentDetailsTimeline timezone', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
			connect: {
				country: 'US',
			},
			currencyData: {
				US: {
					code: 'USD',
					symbol: '$',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
			},
			dateFormat: 'M j, Y',
		};
	} );

	afterEach( () => {
		delete global.wcpaySettings;
	} );

	test( 'renders timeline dates in the site timezone', () => {
		useTimeline.mockReturnValue( {
			timeline: [
				{
					amount: 7900,
					currency: 'USD',
					datetime: 1585589596,
					type: 'authorized',
				},
			],
			timelineError: null,
			isLoading: false,
		} );

		render( <PaymentDetailsTimeline paymentIntentId="pi_test" /> );

		expect(
			screen.getByTestId( 'timeline' ).getAttribute( 'data-timezone' )
		).toBe( 'site' );
	} );
} );
