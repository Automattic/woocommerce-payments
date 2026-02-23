/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ApplePayPreview from '../apple-pay-preview';

describe( 'ApplePayPreview', () => {
	test( 'renders with buttonAttributes', () => {
		const { container } = render(
			<ApplePayPreview
				buttonAttributes={ { height: 55, borderRadius: 8 } }
			/>
		);
		const button = container.querySelector( 'button' );
		expect( button ).toBeInTheDocument();
		expect( button.style.height ).toBe( '55px' );
	} );
} );
