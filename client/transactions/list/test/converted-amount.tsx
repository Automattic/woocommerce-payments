/** @format */
/**
 * External dependencies
 */
import * as React from 'react';
import { render, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ConvertedAmount from '../converted-amount';

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		connect: {
			country: string;
		};
		currencyData: {
			[ key: string ]: {
				code: string;
				symbol: string;
				symbolPosition: string;
				thousandSeparator: string;
				decimalSeparator: string;
				precision: number;
			};
		};
	};
};

describe( 'ConvertedAmount', () => {
	beforeEach( () => {
		global.wcpaySettings = {
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
	} );

	test( 'renders an amount without conversion', () => {
		const { container } = render(
			<ConvertedAmount
				amount={ 100 }
				currency="USD"
				fromAmount={ 100 }
				fromCurrency="USD"
			/>
		);

		expect( container ).toMatchSnapshot();
	} );

	test( 'renders an amount with conversion icon and tooltip', async () => {
		const { container, getByTestId, findByText } = render(
			<ConvertedAmount
				amount={ 100 }
				currency="USD"
				fromAmount={ 200 }
				fromCurrency="MOK"
			/>
		);

		expect( container ).toMatchSnapshot(); // Before tooltip appears.

		const conversionIndicator = getByTestId( 'conversion-indicator' );

		expect( conversionIndicator ).toBeInTheDocument();
		expect( conversionIndicator ).toHaveStyle( 'width: 18px' );
		expect( conversionIndicator ).toHaveStyle( 'height: 18px' );

		fireEvent.mouseEnter( conversionIndicator );
		expect( await findByText( 'Converted from MOK 2.00' ) ).toBeVisible();
		expect( container ).toMatchSnapshot(); // After tooltip appears.

		// Making sure the element layout doesn't get changed after adding the Tooltip
		expect( conversionIndicator ).toHaveStyle( 'width: 18px' );
		expect( conversionIndicator ).toHaveStyle( 'height: 18px' );
	} );
} );
