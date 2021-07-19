/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { useCurrencies, useEnabledCurrencies } from '../../../data';

import CurrencyInformationForMethods from '..';

jest.mock( '../../../data', () => ( {
	useCurrencies: jest.fn(),
	useEnabledCurrencies: jest.fn(),
} ) );

describe( 'CurrencyInformationForMethods', () => {
	beforeEach( () => {
		useCurrencies.mockReturnValue( {
			isLoading: false,
		} );
		useEnabledCurrencies.mockReturnValue( {
			enabledCurrencies: {
				USD: { id: 'usd', code: 'USD' },
			},
		} );
	} );

	it( 'should not display content when the currency data is being loaded', () => {
		useCurrencies.mockReturnValue( {
			isLoading: true,
		} );
		const { container } = render(
			<CurrencyInformationForMethods selectedMethods={ [] } />
		);

		expect(
			screen.queryByText(
				/The selected methods require an additional currency/
			)
		).not.toBeInTheDocument();
		expect( container.firstChild ).toBeNull();
	} );

	it( 'should not display content when the enabled currencies contains Euros', () => {
		useEnabledCurrencies.mockReturnValue( {
			enabledCurrencies: {
				USD: { id: 'usd', code: 'USD' },
				EUR: { id: 'eur', code: 'EUR' },
			},
		} );
		const { container } = render(
			<CurrencyInformationForMethods
				selectedMethods={ [ 'giropay', 'card' ] }
			/>
		);

		expect(
			screen.queryByText(
				/The selected methods require an additional currency/
			)
		).not.toBeInTheDocument();
		expect( container.firstChild ).toBeNull();
	} );

	it( 'should not display content when all the enabled method are not Euro methods', () => {
		const { container } = render(
			<CurrencyInformationForMethods
				selectedMethods={ [ 'card', 'dummy' ] }
			/>
		);

		expect(
			screen.queryByText(
				/The selected methods require an additional currency/
			)
		).not.toBeInTheDocument();
		expect( container.firstChild ).toBeNull();
	} );

	it( 'should display a notice when one of the enabled methods is a Euro method', () => {
		render(
			<CurrencyInformationForMethods
				selectedMethods={ [ 'card', 'giropay' ] }
			/>
		);

		expect(
			screen.queryByText(
				/The selected methods require an additional currency/
			)
		).toBeInTheDocument();
	} );
} );
