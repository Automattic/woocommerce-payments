/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { PaymentMethodsLogos } from '../payment-methods-logos';

describe( 'PaymentMethodsLogos', () => {
	const paymentMethods = [
		{ name: 'visa', component: 'visa.svg' },
		{ name: 'discover', component: 'discover.svg', hasBrandFrame: true },
	];

	it( 'adds the has-brand-frame class only to brand-framed logos', () => {
		render(
			<PaymentMethodsLogos
				maxElements={ 10 }
				paymentMethods={ paymentMethods }
			/>
		);

		expect( screen.getByAltText( 'discover' ) ).toHaveClass(
			'has-brand-frame'
		);
		expect( screen.getByAltText( 'visa' ) ).not.toHaveClass(
			'has-brand-frame'
		);
	} );
} );
