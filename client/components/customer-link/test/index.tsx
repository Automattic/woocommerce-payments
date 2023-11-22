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

function renderCustomer( customer: ChargeBillingDetails, order: OrderDetails ) {
	return render(
		<CustomerLink billing_details={ customer } order_details={ order } />
	);
}

describe( 'CustomerLink', () => {
	test( 'renders a link to a customer from billing details', () => {
		const { container: customerLink } = renderCustomer(
			{
				name: 'Some Name',
				email: 'some@email.com',
			} as any,
			null as any
		);
		expect( customerLink ).toMatchSnapshot();
	} );

	test( 'renders a link to a customer from order details', () => {
		const { container: customerLink } = renderCustomer(
			null as any,
			{
				customer_name: 'Some Name',
				customer_email: 'some@email.com',
			} as any
		);
		expect( customerLink ).toMatchSnapshot();
	} );

	test( 'renders a dash if customer name is undefined', () => {
		const { container: customerLink1 } = renderCustomer(
			null as any,
			null as any
		);
		expect( customerLink1 ).toMatchSnapshot();

		const { container: customerLink2 } = renderCustomer(
			{} as any,
			{} as any
		);
		expect( customerLink2 ).toMatchSnapshot();
	} );
} );
