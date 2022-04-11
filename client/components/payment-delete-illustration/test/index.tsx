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
import WooCardIcon from '../../../gateway-icons/woo-card';

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
			<PaymentDeleteIllustration icon={ WooCardIcon } />
		);
		expect( container ).toMatchSnapshot();
	} );
} );
