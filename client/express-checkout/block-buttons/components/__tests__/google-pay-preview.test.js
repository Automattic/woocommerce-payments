/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import GooglePayPreview from '../google-pay-preview';

describe( 'GooglePayPreview', () => {
	test( 'renders with buttonAttributes', () => {
		const { container } = render(
			<GooglePayPreview
				buttonAttributes={ { height: 55, borderRadius: 8 } }
			/>
		);
		const previewContainer = container.querySelector(
			'#express-checkout-button-preview-googlePay'
		);
		expect( previewContainer ).toBeInTheDocument();
		expect( previewContainer.style.height ).toBe( '55px' );
	} );
} );
