/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import InstantDepositButton from '../';
import { useInstantDeposit } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( { useInstantDeposit: jest.fn() } ) );

const mockUseInstantDeposit = useInstantDeposit as jest.MockedFunction<
	typeof useInstantDeposit
>;

mockUseInstantDeposit.mockReturnValue( {
	deposit: undefined,
	inProgress: false,
	submit: () => null,
} );

// eslint-disable-next-line no-unused-vars
const isButtonDisabled = jest
	.fn()
	.mockReturnValue( false ) // Default response to show enabled button.
	.mockReturnValueOnce( true ); // Response for first test that should have button disabled.

const mockInstantBalance = {
	amount: 12345,
	fee: 123.45,
	net: 12221.55,
	fee_percentage: 1.5,
	transaction_ids: [ 'txn_ABC123', 'txn_DEF456' ],
	currency: 'USD',
} as AccountOverview.InstantBalance;

const mockZeroInstantBalance = {
	amount: 0,
	fee: 0,
	net: 0,
	fee_percentage: 1.5,
	transaction_ids: [],
	currency: 'USD',
} as AccountOverview.InstantBalance;

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
	};
};

describe( 'Instant deposit button and modal', () => {
	beforeEach( () => {
		jest.clearAllMocks();
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
		};
	} );

	test( 'button renders correctly with zero balance', () => {
		const { container } = render(
			<InstantDepositButton instantBalance={ mockZeroInstantBalance } />
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'button renders correctly with balance', () => {
		const { container } = render(
			<InstantDepositButton instantBalance={ mockInstantBalance } />
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'modal renders correctly', () => {
		render(
			<InstantDepositButton instantBalance={ mockInstantBalance } />
		);
		expect(
			screen.queryByRole( 'dialog', { name: /instant deposit/i } )
		).not.toBeInTheDocument();
		fireEvent.click(
			screen.getByRole( 'button', { name: /Deposit available funds/i } )
		);
		const modal = screen.queryByRole( 'dialog', {
			name: /instant deposit/i,
		} );
		expect( modal ).toBeInTheDocument();
		expect( modal ).toMatchSnapshot();
	} );
} );
