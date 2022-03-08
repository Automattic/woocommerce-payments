/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import OrderLink from '../';

function renderOrder( order: OrderDetails ) {
	return render( <OrderLink order={ order } /> );
}

describe( 'OrderLink', () => {
	test( 'renders a link to a valid order', () => {
		const { container: orderLink } = renderOrder( {
			url: 'https://automattic.com/',
			number: 45891,
		} as any );
		expect( orderLink ).toMatchSnapshot();
	} );

	test( 'renders a dash if no order was provided', () => {
		const { container: orderLink } = renderOrder( null as any );
		expect( orderLink ).toMatchSnapshot();
	} );
} );
