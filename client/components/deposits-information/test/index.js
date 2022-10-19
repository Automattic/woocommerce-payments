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
import {
	useAllDepositsOverviews,
	useInstantDeposit,
	useStandardDeposit,
} from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useAllDepositsOverviews: jest.fn(),
	useInstantDeposit: jest.fn(),
	useStandardDeposit: jest.fn(),
} ) );

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

const mockOverviews = ( currencies = null, account = null ) => {
	return useAllDepositsOverviews.mockReturnValue( {
		overviews: {
			currencies: currencies,
			account: account,
		},
		isLoading: null === currencies || ! currencies.length,
	} );
};

useInstantDeposit.mockReturnValue( {
	deposit: undefined,
	inProgress: false,
	submit: () => {},
} );

useStandardDeposit.mockReturnValue( {
	deposit: undefined,
	inProgress: false,
	submit: () => {},
} );

describe( 'Deposits information', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
			connect: {
				country: 'FR',
			},
			currencyData: {
				FR: {
					code: 'EUR',
					symbol: '€',
					symbolPosition: 'right_space',
					thousandSeparator: ' ',
					decimalSeparator: ',',
					precision: 2,
				},
			},
		};
	} );
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders correctly when loading', () => {
		mockOverviews();
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

	test( 'renders correctly with all possible missing details', async () => {
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

		const { findAllByText, findAllByTestId } = render(
			<DepositsInformation />
		);

		expect( await findAllByText( '$0.00' ) ).toHaveLength( 4 );
		( await findAllByTestId( 'extra' ) ).forEach( ( extra ) => {
			expect( extra ).toBeEmptyDOMElement();
		} );
	} );

	test( 'renders instant deposit button only where applicable', async () => {
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

		const { container, findByText } = render( <DepositsInformation /> );
		expect( await findByText( 'Instant deposit' ) ).toBeVisible();
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders instant deposit button when eligible and schedule is manual', async () => {
		const currencyWithInstantDeposit = createMockCurrency( 'usd', {
			instant: {
				amount: 12345,
			},
		} );

		const accountWithDepositNow = createMockAccount( {
			deposits_schedule: {
				delay_days: 4,
				interval: 'manual',
			},
		} );

		mockOverviews( [ currencyWithInstantDeposit ], accountWithDepositNow );

		const { container, getByRole, queryByRole } = render(
			<DepositsInformation />
		);
		const instantDepositButton = getByRole( 'button', {
			name: 'Instant deposit',
		} );
		expect( instantDepositButton ).toBeVisible();
		const depositNowButton = queryByRole( 'button', {
			name: 'Deposit funds',
		} );
		expect( depositNowButton ).not.toBeInTheDocument();
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders standard deposit button enabled when deposits schedule is manual', async () => {
		const accountWithDepositNow = createMockAccount( {
			deposits_schedule: {
				delay_days: 4,
				interval: 'manual',
			},
		} );

		mockOverviews( [ createMockCurrency( 'aud' ) ], accountWithDepositNow );

		const { container, getByRole } = render( <DepositsInformation /> );
		const depositNowButton = getByRole( 'button', {
			name: 'Deposit funds',
		} );
		expect( depositNowButton ).toBeVisible();
		expect( depositNowButton ).not.toHaveAttribute( 'disabled' );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders standard deposit button disabled when no available balance', async () => {
		const accountWithDepositNow = createMockAccount( {
			deposits_schedule: {
				delay_days: 4,
				interval: 'manual',
			},
		} );

		const currency = createMockCurrency( 'aud', {
			available: {
				amount: 0,
			},
		} );

		mockOverviews( [ currency ], accountWithDepositNow );

		const { container, getByRole } = render( <DepositsInformation /> );
		const depositNowButton = getByRole( 'button', {
			name: 'Deposit funds',
		} );
		expect( depositNowButton ).toBeVisible();
		expect( depositNowButton ).toHaveAttribute( 'disabled' );
		expect( container ).toMatchSnapshot();
	} );
} );
