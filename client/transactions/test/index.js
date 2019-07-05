/** @format */
/**
 * External dependencies
 */
import renderer from 'react-test-renderer';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch' );

/**
 * Internal dependencies
 */
import { TransactionsPage } from '../';

describe( 'Transactions page', () => {
	test( 'renders correctly', () => {
        apiFetch.mockResolvedValue( {
            data: [
                {
                    created: 1572590800,
                    type: 'refund',
                    amount: 1000,
                    fee: 50,
                    // available_on: 1573199200,
                },
                {
                    created: 1572580800,
                    type: 'charge',
                    source: {
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
        } );

		const tree = renderer.create( <TransactionsPage /> ).toJSON();
		expect( tree ).toMatchSnapshot();
	} );
} );
