/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import InstantDepositButton from '../';
import { useInstantDeposit } from 'data';

jest.mock( 'data', () => ( { useInstantDeposit: jest.fn() } ) );

useInstantDeposit.mockReturnValue( {
	deposit: undefined,
	isLoading: false,
	submit: () => {},
} );

const instantDepositBalance = {
	balance: {
		amount: 12345,
		fee: 123.45,
		net: 12221.55,
		// eslint-disable-next-line camelcase
		transaction_ids: [ 'txn_ABC123', 'txn_DEF456' ],
	},
};

describe( 'Instant deposit button and modal', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		global.wcpaySettings = { zeroDecimalCurrencies: [] };
	} );

	test( 'button renders correctly', () => {
		const { container } = render(
			<InstantDepositButton { ...instantDepositBalance } />
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'modal renders correctly', () => {
		render( <InstantDepositButton { ...instantDepositBalance } /> );
		expect(
			screen.queryByRole( 'dialog', { name: /instant deposit/i } )
		).not.toBeInTheDocument();
		fireEvent.click(
			screen.getByRole( 'button', { name: /instant deposit/i } )
		);
		const modal = screen.queryByRole( 'dialog', {
			name: /instant deposit/i,
		} );
		expect( modal ).toBeInTheDocument();
		expect( modal ).toMatchSnapshot();
	} );
} );
