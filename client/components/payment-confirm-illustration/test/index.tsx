/** @format */

/**
 * External dependencies
 */
import * as React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentConfirmIllustration from '..';
import WooCardIcon from 'assets/images/payment-methods/woo.svg';

describe( 'PaymentConfirmIllustration', () => {
	test( 'renders without props', () => {
		const { container } = render( <PaymentConfirmIllustration /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with borders', () => {
		const { container } = render(
			<PaymentConfirmIllustration hasBorder />
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with icon', () => {
		const { container } = render(
			<PaymentConfirmIllustration
				icon={ () => <img src={ WooCardIcon } alt="WooPay" /> }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
