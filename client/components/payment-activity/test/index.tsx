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
		lifetimeTPV: number;
	};
};

describe( 'PaymentActivity component', () => {
	it( 'should render', () => {
		const { container } = render( <PaymentActivity /> );

		expect( container ).toMatchSnapshot();
	} );

	it( 'should render an empty state', () => {
		global.wcpaySettings.lifetimeTPV = 0;

		const { container, getByText } = render( <PaymentActivity /> );

		expect( getByText( 'No payments…yet!' ) ).toBeInTheDocument();
		expect( container ).toMatchSnapshot();
	} );
} );
