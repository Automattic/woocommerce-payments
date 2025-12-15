/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { PaymentMethodsLogos } from '../payment-methods-logos';

const mockPaymentMethods = [
	{ name: 'Visa', component: 'visa.png' },
	{ name: 'MasterCard', component: 'mastercard.png' },
	{ name: 'PayPal', component: 'paypal.png' },
	{ name: 'Amex', component: 'amex.png' },
	{ name: 'Discover', component: 'discover.png' },
];

describe( 'PaymentMethodsLogos', () => {
	it( 'renders without crashing', () => {
		render(
			<PaymentMethodsLogos
				maxElements={ 5 }
				paymentMethods={ mockPaymentMethods }
			/>
		);
		const logoContainer = screen.getByTestId( 'payment-methods-logos' );
		expect( logoContainer ).toBeTruthy();
	} );

	it( 'displays correct number of logos based on maxElements', () => {
		render(
			<PaymentMethodsLogos
				maxElements={ 3 }
				paymentMethods={ mockPaymentMethods }
			/>
		);
		const logos = screen.queryAllByRole( 'img' );
		expect( logos ).toHaveLength( 3 );
	} );

	it( 'shows popover indicator when there are more payment methods than maxElements', () => {
		render(
			<PaymentMethodsLogos
				maxElements={ 3 }
				paymentMethods={ mockPaymentMethods }
			/>
		);
		const popoverIndicator = screen.queryByText(
			`+ ${ mockPaymentMethods.length - 3 }`
		);
		expect( popoverIndicator ).toBeTruthy();
	} );

	it( 'opens popover on button click', async () => {
		render(
			<PaymentMethodsLogos
				maxElements={ 3 }
				paymentMethods={ mockPaymentMethods }
			/>
		);
		const button = screen.getByTestId( 'payment-methods-logos' );

		fireEvent.click( button );

		const popover = await screen.findByTestId( 'payment-methods-popover' );
		expect( popover ).toBeTruthy();
	} );

	it( 'handles keyboard navigation', async () => {
		render(
			<PaymentMethodsLogos
				maxElements={ 3 }
				paymentMethods={ mockPaymentMethods }
			/>
		);
		const button = screen.getByTestId( 'payment-methods-logos' );

		fireEvent.keyDown( button, { key: 'Enter' } );

		const popover = await screen.findByTestId( 'payment-methods-popover' );
		expect( popover ).toBeTruthy();
	} );

	it( 'does not show popover indicator when there are fewer payment methods than maxElements', () => {
		render(
			<PaymentMethodsLogos
				maxElements={ 10 }
				paymentMethods={ mockPaymentMethods }
			/>
		);
		const popoverIndicator = screen.queryByText( /^\+\s*\d+$/ );
		expect( popoverIndicator ).toBeNull();

		const logos = screen.getAllByRole( 'img' );
		expect( logos ).toHaveLength( mockPaymentMethods.length );
	} );

	it( 'does not show popover when there are fewer payment methods than maxElements', async () => {
		render(
			<PaymentMethodsLogos
				maxElements={ 10 }
				paymentMethods={ mockPaymentMethods }
			/>
		);

		const button = screen.getByTestId( 'payment-methods-logos' );

		fireEvent.click( button );

		const popover = screen.queryByTestId( 'payment-methods-popover' );
		expect( popover ).toBeNull();
	} );
} );
