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
		const dispute = {
			amount: 1000,
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
		};

		const info = shallow(
			<Info dispute={ dispute } />
		);
		expect( info ).toMatchSnapshot();
	} );
} );
