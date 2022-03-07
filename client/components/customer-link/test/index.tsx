/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import CustomerLink from '../';
import { ChargeBillingDetails } from 'wcpay/types/charges';

function renderCustomer( customer: ChargeBillingDetails ) {
	return render( <CustomerLink customer={ customer } /> );
}

describe( 'CustomerLink', () => {
	test( 'renders a link to a customer with name and email', () => {
		const { container: customerLink } = renderCustomer( {
			name: 'Some Name',
			email: 'some@email.com',
		} as any );
		expect( customerLink ).toMatchSnapshot();
	} );

	test( 'renders a dash if customer name is undefined', () => {
		const { container: customerLink1 } = renderCustomer( null as any );
		expect( customerLink1 ).toMatchSnapshot();

		const { container: customerLink2 } = renderCustomer( {} as any );
		expect( customerLink2 ).toMatchSnapshot();
	} );
} );
