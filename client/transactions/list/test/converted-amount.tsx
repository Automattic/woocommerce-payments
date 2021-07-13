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
	};
};

describe( 'ConvertedAmount', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
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

		fireEvent.mouseEnter( getByTestId( 'conversion-indicator' ) );

		expect( await findByText( 'Converted from MOK 2.00' ) ).toBeVisible();
		expect( container ).toMatchSnapshot(); // After tooltip appears.
	} );
} );
