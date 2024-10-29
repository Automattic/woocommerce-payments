/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentDataTile from '../payment-data-tile';

declare const global: {
	wcpaySettings: {
		accountDefaultCurrency: string;
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
	};
};

describe( 'PaymentDataTile', () => {
	global.wcpaySettings = {
		accountDefaultCurrency: 'usd',
		zeroDecimalCurrencies: [],
		connect: {
			country: 'US',
		},
		currencyData: {
			US: {
				code: 'USD',
				symbol: '$',
				symbolPosition: 'left',
				thousandSeparator: ',',
				decimalSeparator: '.',
				precision: 2,
			},
		},
	};

	test( 'renders correctly', () => {
		const { container } = render(
			<PaymentDataTile
				id="total-payment"
				currencyCode="usd"
				label="Total payment volume"
			/>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders label correctly', () => {
		const label = 'Total payment volume';
		render(
			<PaymentDataTile
				id="total-payment"
				currencyCode="usd"
				label={ label }
				amount={ 123 }
			/>
		);
		expect( screen.getByText( label ) ).toBeInTheDocument();
		expect( screen.getByLabelText( label ) ).toHaveTextContent( '$1.23' );
	} );

	test( 'renders amount correctly', () => {
		const amount = 10000;
		const currencyCode = 'usd';
		render(
			<PaymentDataTile
				id="charges-test-tile"
				label="Charges"
				amount={ amount }
				currencyCode={ currencyCode }
			/>
		);
		const amountElement = screen.getByText( '$100.00' );
		expect( amountElement ).toBeInTheDocument();
	} );

	test( 'renders report link correctly', () => {
		const reportLink = 'https://example.com/report';
		render(
			<PaymentDataTile
				id="charges-test-tile"
				label="Charges"
				amount={ 10000 }
				currencyCode="usd"
				reportLink={ reportLink }
			/>
		);
		const reportLinkElement = screen.getByRole( 'link', {
			name: 'View report',
		} );
		expect( reportLinkElement ).toBeInTheDocument();
		expect( reportLinkElement ).toHaveAttribute( 'href', reportLink );
	} );
} );
