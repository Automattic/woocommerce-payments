/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import OrderLink from '../';

describe( 'OrderLink', () => {
	test( 'renders a link to a valid order', () => {
		const { container: orderLink } = renderOrder( { url: 'https://automattic.com/', number: '45891' } );
		expect( orderLink ).toMatchSnapshot();
	} );

	test( 'renders a dash if no order was provided', () => {
		const { container: orderLink } = renderOrder( null );
		expect( orderLink ).toMatchSnapshot();
	} );

	function renderOrder( order ) {
		return render( <OrderLink order={ order } /> );
	}
} );

