/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentActivity from '..';

declare const global: {
	wcpaySettings: {
		all_time_tpv: number;
	};
};

describe( 'PaymentActivity component', () => {
	it( 'should render', () => {
		const { container } = render( <PaymentActivity /> );

		expect( container ).toMatchSnapshot();
	} );

	it( 'should render an empty state', () => {
		global.wcpaySettings.all_time_tpv = 0;

		const { container, getByText } = render( <PaymentActivity /> );

		getByText( 'No payments...yet!' );
		expect( container ).toMatchSnapshot();
	} );
} );
