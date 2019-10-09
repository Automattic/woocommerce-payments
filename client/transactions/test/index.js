/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import { TransactionsList } from '../';

describe( 'Transactions list', () => {
	test( 'renders correctly', () => {
		const transactions = {
			data: [
				{
					id: 'txn_j23jda9JJa',
					created: 1572590800,
					type: 'refund',
					source: {
						object: 'refund',
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
						object: 'charge',
						paymentMethodDetails: {
							card: {
								brand: 'visa',
							},
						},
						billingDetails: {
							name: 'My name',
							email: 'a@b.com',
							address: {
								country: 'US',
							},
						},
						outcome: {
							riskLevel: 'normal',
						},
					},
					amount: 1500,
					fee: 50,
					// available_on: 1573189200,
				},
			],
		};

		const list = shallow(
			<TransactionsList
				transactions={ transactions }
				isLoading={ false }
			/>
		);
		expect( list ).toMatchSnapshot();
	} );
} );
