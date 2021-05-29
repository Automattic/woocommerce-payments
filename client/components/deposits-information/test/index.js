/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import { merge } from 'lodash';

/**
 * Internal dependencies
 */
import DepositsInformation from '..';
import { useAllDeposistsOverviews, useInstantDeposit } from 'data';

jest.mock( 'data', () => ( {
	useAllDeposistsOverviews: jest.fn(),
	useInstantDeposit: jest.fn(),
} ) );

/* eslint-disable camelcase */
const createMockAccount = ( account = {} ) =>
	merge(
		{
			default_currency: 'eur',
			deposits_disabled: false,
			deposits_schedule: {
				delay_days: 7,
				interval: 'weekly',
				weekly_anchor: 'thursday',
			},
		},
		account
	);

const createMockCurrency = ( currencyCode, extra = {} ) =>
	merge(
		{
			currency: currencyCode,
			lastPaid: {
				id: 'po_...',
				date: 1619395200000,
				amount: 3160,
			},
			nextScheduled: {
				id: 'wcpay_estimated_weekly_eur_1622678400',
				date: 1622678400000,
				amount: 3343,
			},
			pending: {
				amount: 3343,
				deposits_count: 2,
			},
			available: {
				amount: 2030,
			},
		},
		extra
	);
/* eslint-enable camelcase */

const mockOverviews = ( currencies = null, account = null ) => {
	return useAllDeposistsOverviews.mockReturnValue( {
		overviews: {
			currencies: currencies,
			account: account,
		},
		isLoading: null === currencies || ! currencies.length,
	} );
};

useInstantDeposit.mockReturnValue( {
	deposit: undefined,
	isLoading: false,
	submit: () => {},
} );

describe( 'Deposits information', () => {
	beforeEach( () => {
		global.wcpaySettings = { zeroDecimalCurrencies: [] };
	} );
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders correctly when loading', () => {
		mockOverviews();
		const { container } = render( <DepositsInformation /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders correctly with a single currency', () => {
		mockOverviews( [ createMockCurrency( 'usd' ) ], createMockAccount() );

		const { container } = render( <DepositsInformation /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders correctly with multiple currencies', () => {
		mockOverviews(
			[ createMockCurrency( 'usd' ), createMockCurrency( 'eur' ) ],
			createMockAccount()
		);

		const { container } = render( <DepositsInformation /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders correctly with all possible missing details', () => {
		// Everything should be zeros, without extra details or errors.
		mockOverviews(
			[
				createMockCurrency( 'usd', {
					pending: null,
					nextScheduled: null,
					lastPaid: null,
					available: null,
				} ),
			],
			createMockAccount()
		);

		const { container } = render( <DepositsInformation /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders instant deposit button only where applicable', () => {
		const currencyWithInstantDeposit = createMockCurrency( 'usd', {
			instant: {
				amount: 12345,
			},
		} );

		const currencyWithoutInstantDeposit = createMockCurrency( 'eur' );

		mockOverviews(
			// Only one of the currencies in the snapshot should include the instant deposit button.
			[ currencyWithInstantDeposit, currencyWithoutInstantDeposit ],
			createMockAccount()
		);

		const { container } = render( <DepositsInformation /> );
		expect( container ).toMatchSnapshot();
	} );
} );
