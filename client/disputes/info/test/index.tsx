/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import Info from '../';

describe( 'Dispute info', () => {
	beforeEach( () => {
		// eslint-disable-next-line @typescript-eslint/ban-ts-comment
		// @ts-ignore
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
		};
	} );

	test( 'renders correctly', () => {
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

		const { container: info } = render(
			// eslint-disable-next-line @typescript-eslint/ban-ts-comment
			// @ts-ignore
			<Info dispute={ dispute } />
		);
		expect( info ).toMatchSnapshot();
	} );

	test( 'renders loading state', () => {
		// eslint-disable-next-line @typescript-eslint/ban-ts-comment
		// @ts-ignore
		const { container: info } = render( <Info isLoading={ true } /> );
		expect( info ).toMatchSnapshot();
	} );
} );
