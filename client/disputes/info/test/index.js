/** @format */

/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import Info from '../';

describe( 'Dispute info', () => {
	test( 'renders correctly', () => {
		/* eslint-disable camelcase */
		const dispute = {
			amount: 1000,
			currency: 'usd',
			created: 1572590800,
			evidence_details: {
				due_by: 1573199200,
			},
			reason: 'fraudulent',
			status: 'needs_response',
			order: {
				number: '1',
				url: 'http://test.local/order/1',
			},
			charge: {
				id: 'ch_mock',
				billing_details: {
					name: 'Mock customer',
				},
			},
		};
		/* eslint-enable camelcase */

		const info = shallow( <Info dispute={ dispute } /> );
		expect( info ).toMatchSnapshot();
	} );

	test( 'renders loading state', () => {
		const info = shallow( <Info isLoading={ true } /> );
		expect( info ).toMatchSnapshot();
	} );
} );
