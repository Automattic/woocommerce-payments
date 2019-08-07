/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import TransactionsPage from '../';

describe( 'Transactions list', () => {
	test( 'renders correctly', () => {
		const transactions = {
			data: [
				{
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
					created: 1572580800,
					type: 'charge',
					source: {
						object: 'charge',
						payment_method_details: {
							card: {
								brand: 'visa',
							},
						},
						billing_details: {
							name: 'My name',
							email: 'a@b.com',
							address: {
								country: 'US',
							},
						},
						outcome: {
							risk_level: 'normal',
						},
					},
					amount: 1500,
					fee: 50,
					// available_on: 1573189200,
				},
			],
		};

		const list = shallow(
			<TransactionsPage
				transactions={ transactions }
				isLoading={ false }
			/>
		);
		expect( list ).toMatchSnapshot();
	} );
} );
