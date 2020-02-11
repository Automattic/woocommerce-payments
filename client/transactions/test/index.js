/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import { TransactionsList } from '../';
import { useTransactions, useTransactionsSummary } from '../../data';

jest.mock( '../../data', () => ( {
	useTransactions: jest.fn(),
	useTransactionsSummary: jest.fn(),
} ) );

const mockTransactions = [
	{
		// eslint-disable-next-line camelcase
		transaction_id: 'txn_j23jda9JJa',
		date: '2020-01-02 17:46:02',
		type: 'refund',
		source: 'visa',
		order: {
			number: 123,
			url: 'https://example.com/order/123',
		},
		// eslint-disable-next-line camelcase
		customer_name: 'Another customer',
		// eslint-disable-next-line camelcase
		customer_email: 'another@customer.com',
		// eslint-disable-next-line camelcase
		customer_country: 'US',
		// eslint-disable-next-line camelcase
		charge_id: 'ch_j23w39dsajda',
		amount: 1000,
		fees: 50,
		net: 950,
		currency: 'usd',
		// eslint-disable-next-line camelcase
		risk_level: 0,
		// eslint-disable-next-line camelcase
		deposit_id: null,
	},
	{
		// eslint-disable-next-line camelcase
		transaction_id: 'txn_oa9kaKaa8',
		date: '2020-01-05 04:22:59',
		date_available: '2020-01-07 00:00:00',
		type: 'charge',
		source: 'mastercard',
		order: {
			number: 125,
			url: 'https://example.com/order/125',
		},
		// eslint-disable-next-line camelcase
		customer_name: 'My name',
		// eslint-disable-next-line camelcase
		customer_email: 'a@b.com',
		// eslint-disable-next-line camelcase
		customer_country: 'US',
		// eslint-disable-next-line camelcase
		charge_id: 'ch_j239jda',
		amount: 1500,
		fees: 50,
		net: 1450,
		currency: 'usd',
		// eslint-disable-next-line camelcase
		risk_level: 2,
		// eslint-disable-next-line camelcase
		deposit_id: 'po_mock',
	},
];

describe( 'Transactions list', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders correctly', () => {
		useTransactions.mockReturnValue( {
			transactions: mockTransactions,
			isLoading: false,
		} );

		useTransactionsSummary.mockReturnValue( {
			transactionsSummary: {
				count: 10,
				fees: 100,
				total: 1000,
				net: 900,
			},
			isLoading: false,
		} );

		const list = shallow(
			<TransactionsList />
		);
		expect( list ).toMatchSnapshot();
	} );

	test( 'renders correctly when filtered to deposit', () => {
		useTransactions.mockReturnValue( {
			transactions: mockTransactions.filter( ( txn ) => txn.deposit_id === 'po_mock' ),
			isLoading: false,
		} );

		useTransactionsSummary.mockReturnValue( {
			transactionsSummary: {
				count: 3,
				fees: 30,
				total: 300,
				net: 270,
			},
			isLoading: false,
		} );

		const list = shallow(
			<TransactionsList depositId="po_mock" />
		);
		expect( list ).toMatchSnapshot();
		expect( useTransactions.mock.calls[ 0 ][ 1 ] ).toBe( 'po_mock' );
	} );
} );
