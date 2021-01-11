/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import CustomerLink from '../';

describe( 'CustomerLink', () => {
	test( 'renders a link to a customer with name and email', () => {
		const { container: customerLink } = renderCustomer( {
			name: 'Some Name',
			email: 'some@email.com',
		} );
		expect( customerLink ).toMatchSnapshot();
	} );

	test( 'renders a dash if customer name is undefined', () => {
		const { container: customerLink1 } = renderCustomer( null );
		expect( customerLink1 ).toMatchSnapshot();

		const { container: customerLink2 } = renderCustomer( {} );
		expect( customerLink2 ).toMatchSnapshot();
	} );

	function renderCustomer( customer ) {
		return render( <CustomerLink customer={ customer } /> );
	}
} );
