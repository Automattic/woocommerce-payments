/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentActivity from '..';

describe( 'PaymentActivity component', () => {
	it( 'should render', () => {
		const { getByText, container } = render( <PaymentActivity /> );

		expect( getByText( 'Your payment activity' ) ).toBeInTheDocument();
		expect(
			container.querySelector( '.wcpay-payments-activity__card' )
		).toBeInTheDocument();
		expect(
			container.querySelector( '.wcpay-payments-activity__card__header' )
		).toBeInTheDocument();
		expect(
			container.querySelector( '.wcpay-payments-activity__card__body' )
		).toBeInTheDocument();
	} );
} );
