/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import { DisputeDetails } from '../details';

describe( 'Dispute details screen', () => {
	test( 'renders correctly for fraudulent dispute', () => {
		const dispute = {
			id: 'dp_asdfghjkl',
			amount: 1000,
			created: 1572590800,
			// eslint-disable-next-line camelcase
			evidence_details: {
				// eslint-disable-next-line camelcase
				due_by: 1573199200,
			},
			reason: 'fraudulent',
			status: 'needs_response',
			order: {
				number: '1',
				url: 'http://test.local/order/1',
			},
		};

		const list = shallow(
			<DisputeDetails
				dispute={ dispute }
				showPlaceholder={ false }
			/>
		);
		expect( list ).toMatchSnapshot();
	} );

	test( 'renders correctly for general dispute', () => {
		const dispute = {
			id: 'dp_zxcvbnm',
			amount: 1050,
			created: 1572480800,
			// eslint-disable-next-line camelcase
			evidence_details: {
				// eslint-disable-next-line camelcase
				due_by: 1573099200,
			},
			reason: 'general',
			status: 'under_review',
			// dispute without order information
		};

		const list = shallow(
			<DisputeDetails
				dispute={ dispute }
				showPlaceholder={ false }
			/>
		);
		expect( list ).toMatchSnapshot();
	} );
} );
