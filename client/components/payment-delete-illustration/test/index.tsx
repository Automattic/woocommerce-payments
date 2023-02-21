/** @format */

/**
 * External dependencies
 */
import * as React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentDeleteIllustration from '..';
import WooCardIcon from 'assets/images/payment-methods/woo.svg';

describe( 'PaymentDeleteIllustration', () => {
	test( 'renders without props', () => {
		const { container } = render( <PaymentDeleteIllustration /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with borders', () => {
		const { container } = render( <PaymentDeleteIllustration hasBorder /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders with icon', () => {
		const { container } = render(
			<PaymentDeleteIllustration
				icon={ () => <img src={ WooCardIcon } alt="WooPay" /> }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
