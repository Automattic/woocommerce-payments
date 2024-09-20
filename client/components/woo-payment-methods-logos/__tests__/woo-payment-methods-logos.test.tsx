/**
 * External dependencies
 */
import React, { render, screen, fireEvent, act } from '@testing-library/react';
import { describe, test, expect } from '@jest/globals';
import { WooPaymentMethodsLogos } from '../woo-payment-methods-logos';

const mockPaymentMethods = [
	{ name: 'Visa', component: 'visa.png' },
	{ name: 'MasterCard', component: 'mastercard.png' },
	{ name: 'PayPal', component: 'paypal.png' },
	{ name: 'Amex', component: 'amex.png' },
	{ name: 'Discover', component: 'discover.png' },
];

describe( 'WooPaymentMethodsLogos', () => {
	test( 'renders without crashing', () => {
		render(
			<WooPaymentMethodsLogos
				maxElements={ 5 }
				paymentMethods={ mockPaymentMethods }
			/>
		);
		const logoContainer = screen.getByTestId( 'payment-methods-logos' );
		expect( logoContainer ).toBeTruthy();
	} );

	test( 'displays correct number of logos based on maxElements', () => {
		render(
			<WooPaymentMethodsLogos
				maxElements={ 3 }
				paymentMethods={ mockPaymentMethods }
			/>
		);
		const logos = screen.queryAllByRole( 'img' );
		expect( logos ).toHaveLength( 3 );
	} );

	test( 'shows popover indicator when there are more payment methods than maxElements', () => {
		render(
			<WooPaymentMethodsLogos
				maxElements={ 3 }
				paymentMethods={ mockPaymentMethods }
			/>
		);
		const popoverIndicator = screen.queryByText(
			`+ ${ mockPaymentMethods.length - 3 }`
		);
		expect( popoverIndicator ).toBeTruthy();
	} );

	test( 'opens popover on button click', async () => {
		render(
			<WooPaymentMethodsLogos
				maxElements={ 3 }
				paymentMethods={ mockPaymentMethods }
			/>
		);
		const button = screen.getByTestId( 'payment-methods-logos' );

		await act( () => {
			fireEvent.click( button );
		} );

		const popover = await screen.findByTestId( 'payment-methods-popover' );
		expect( popover ).toBeTruthy();
	} );

	test( 'handles keyboard navigation', async () => {
		render(
			<WooPaymentMethodsLogos
				maxElements={ 3 }
				paymentMethods={ mockPaymentMethods }
			/>
		);
		const button = screen.getByTestId( 'payment-methods-logos' );

		await act( () => {
			fireEvent.keyDown( button, { key: 'Enter' } );
		} );

		const popover = await screen.findByTestId( 'payment-methods-popover' );
		expect( popover ).toBeTruthy();
	} );

	test( 'does not show popover indicator when there are fewer payment methods than maxElements', () => {
		render(
			<WooPaymentMethodsLogos
				maxElements={ 10 }
				paymentMethods={ mockPaymentMethods }
			/>
		);
		const popoverIndicator = screen.queryByText( /^\+\s*\d+$/ );
		expect( popoverIndicator ).toBeNull();

		const logos = screen.getAllByRole( 'img' );
		expect( logos ).toHaveLength( mockPaymentMethods.length );
	} );

	test( 'does not show popover when there are fewer payment methods than maxElements', async () => {
		render(
			<WooPaymentMethodsLogos
				maxElements={ 10 }
				paymentMethods={ mockPaymentMethods }
			/>
		);

		const button = screen.getByTestId( 'payment-methods-logos' );

		await act( () => {
			fireEvent.click( button );
		} );

		const popover = screen.queryByTestId( 'payment-methods-popover' );
		expect( popover ).toBeNull();
	} );
} );
