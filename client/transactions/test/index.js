/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import { TransactionsList } from '../';
import { useTransactionsPage } from '../../data';

jest.mock( '../../data', () => ( { useTransactionsPage: jest.fn() } ) );

describe( 'Transactions list', () => {
	test( 'renders correctly', () => {
		useTransactionsPage.mockReturnValue( {
			transactions: [
				{
					id: 'txn_j23jda9JJa',
					created: 1572590800,
					type: 'refund',
					source: {
						object: 'refund',
						charge: {
							id: 'ch_j23w39dsajda',
							object: 'charge',
							// eslint-disable-next-line camelcase
							payment_method_details: {
								card: {
									brand: 'visa',
								},
							},
							// eslint-disable-next-line camelcase
							billing_details: {
								name: 'Another customer',
								email: 'another@customer.com',
								address: {
									country: 'US',
								},
							},
							outcome: {
								// eslint-disable-next-line camelcase
								risk_level: 'high',
							},
						},
					},
					amount: 1000,
					fee: 50,
					// available_on: 1573199200,
				},
				{
					id: 'txn_oa9kaKaa8',
					created: 1572580800,
					type: 'charge',
					source: {
						id: 'ch_j239jda',
						object: 'charge',
						// eslint-disable-next-line camelcase
						payment_method_details: {
							card: {
								brand: 'mastercard',
							},
						},
						// eslint-disable-next-line camelcase
						billing_details: {
							name: 'My name',
							email: 'a@b.com',
							address: {
								country: 'US',
							},
						},
						outcome: {
							// eslint-disable-next-line camelcase
							risk_level: 'normal',
						},
					},
					amount: 1500,
					fee: 50,
					// available_on: 1573189200,
				},
			],
			loading: false,
		} );

		const list = shallow(
			<TransactionsList />
		);
		expect( list ).toMatchSnapshot();
	} );
} );
