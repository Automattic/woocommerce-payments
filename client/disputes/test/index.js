/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import { DisputesList } from '../';

describe( 'Disputes list', () => {
	test( 'renders correctly', () => {
		const disputes = {
			data: [
				{
					id: 'dp_asdfghjkl',
					amount: 1000,
					currency: 'usd',
					created: 1572590800,
					// eslint-disable-next-line camelcase
					evidence_details: {
						// eslint-disable-next-line camelcase
						due_by: 1573199200,
					},
					reason: 'fraudulent',
					status: 'needs_response',
					charge: {
						id: 'ch_mock',
						// eslint-disable-next-line camelcase
						payment_method_details: {
							card: {
								brand: 'visa',
							},
						},
						// eslint-disable-next-line camelcase
						billing_details: {
							name: 'Mock customer',
							email: 'mock@customer.net',
							address: {
								country: 'US',
							},
						},
					},
					order: {
						number: '1',
						url: 'http://test.local/order/1',
					},
				},
				{
					id: 'dp_zxcvbnm',
					amount: 1050,
					currency: 'usd',
					created: 1572480800,
					// eslint-disable-next-line camelcase
					evidence_details: {
						// eslint-disable-next-line camelcase
						due_by: 1573099200,
					},
					reason: 'general',
					status: 'under_review',
					// dispute without order or charge information
				},
			],
		};

		const list = shallow(
			<DisputesList
				disputes={ disputes }
				showPlaceholder={ false }
			/>
		);
		expect( list ).toMatchSnapshot();
	} );
} );
