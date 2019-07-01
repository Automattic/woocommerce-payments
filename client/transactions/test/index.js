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
import { TransactionsList } from '../';

describe( 'Transactions list', () => {
	test( 'renders correctly', () => {
        apiFetch.mockResolvedValue( {
            data: [
                {
                    type: 'charge',
                    status: 'pending',
                    description: 'Test description',
                    amount: 1500,
                    fee: 50,
                    created: 1572580800,
                    available_on: 1573189200,
                },
            ],
        } );

		const tree = renderer.create( <TransactionsList /> ).toJSON();
		expect( tree ).toMatchSnapshot();
	} );
} );
