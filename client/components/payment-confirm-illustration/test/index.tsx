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
import WooCardIcon from 'assets/images/cards/woo-card.svg?asset';

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
				icon={ ( props ) => (
					<img src={ WooCardIcon } alt="" { ...props } />
				) }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
