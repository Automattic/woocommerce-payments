/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AmazonPayPreview from '../amazon-pay-preview';

describe( 'AmazonPayPreview', () => {
	test( 'renders with buttonAttributes', () => {
		const { container } = render(
			<AmazonPayPreview
				buttonAttributes={ { height: 55, borderRadius: 8 } }
			/>
		);
		const button = container.querySelector( 'button' );
		expect( button ).toBeInTheDocument();
		expect( button.style.height ).toBe( '55px' );
	} );
} );
