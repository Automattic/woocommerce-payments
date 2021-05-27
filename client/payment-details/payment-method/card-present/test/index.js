/* eslint-disable camelcase */
/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';
/**
 * Internal dependencies
 */
import CardPresentDetails from '../';

describe( 'CardPresentDetails', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		global.wcSettings = {
			countries: {
				US: 'United States of America',
			},
		};
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
		};
	} );

	afterEach( () => {
		delete global.wcSettings;
		delete global.wcpaySettings;
	} );

	test( 'renders loading', () => {
		const charge = {
			payment_method_details: {
				type: 'card_present',
				card_present: {
					last4: '9999',
					fingerprint: '123456789abc',
					exp_month: '11',
					exp_year: '2023',
					funding: 'funding',
					network: 'network',
					country: 'US',
				},
			},
			billing_details: {
				name: 'foo',
				email: 'bar',
				formattedAddress: 'baz',
			},
		};

		const { container } = render(
			<CardPresentDetails charge={ charge } isLoading={ false } />
		);

		expect( container ).toMatchSnapshot();
	} );
} );
