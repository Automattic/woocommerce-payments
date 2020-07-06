/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { DepositOverview } from '../';
import { useDeposit } from 'data';

jest.mock( 'data', () => ( {
	useDeposit: jest.fn(),
} ) );

const mockDeposit = {
	id: 'po_mock',
	date: '2020-01-02 17:46:02',
	type: 'deposit',
	amount: 2000,
	status: 'paid',
	bankAccount: 'MOCK BANK •••• 1234 (USD)',
};

describe( 'Deposit overview', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders correctly', () => {
		useDeposit.mockReturnValue( {
			deposit: mockDeposit,
			isLoading: false,
		} );

		const { container: overview } = render(
			<DepositOverview depositId="po_mock" />
		);
		expect( overview ).toMatchSnapshot();
	} );
} );
