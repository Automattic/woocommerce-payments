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
import WCPaySettingsContext from '../../../settings/wcpay-settings-context';

jest.mock( '../../../data', () => ( {
	useCurrencies: jest.fn(),
	useEnabledCurrencies: jest.fn(),
} ) );

jest.mock( '@wordpress/a11y', () => ( {
	...jest.requireActual( '@wordpress/a11y' ),
	speak: jest.fn(),
} ) );

const FlagsContextWrapper = ( { children, multiCurrency = true } ) => (
	<WCPaySettingsContext.Provider
		value={ { featureFlags: { multiCurrency } } }
	>
		{ children }
	</WCPaySettingsContext.Provider>
);

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

	it( 'should not display content when the feature flag is disabled', () => {
		const { container } = render(
			<FlagsContextWrapper multiCurrency={ false }>
				<CurrencyInformationForMethods
					selectedMethods={ [ 'card', 'giropay' ] }
				/>
			</FlagsContextWrapper>
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'should not display content when the currency data is being loaded', () => {
		useCurrencies.mockReturnValue( {
			isLoading: true,
		} );
		const { container } = render(
			<FlagsContextWrapper>
				<CurrencyInformationForMethods
					selectedMethods={ [ 'card', 'giropay' ] }
				/>
			</FlagsContextWrapper>
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
			<FlagsContextWrapper>
				<CurrencyInformationForMethods
					selectedMethods={ [ 'giropay', 'card' ] }
				/>
			</FlagsContextWrapper>
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
			<FlagsContextWrapper>
				<CurrencyInformationForMethods
					selectedMethods={ [ 'card', 'dummy' ] }
				/>
			</FlagsContextWrapper>
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
			<FlagsContextWrapper>
				<CurrencyInformationForMethods
					selectedMethods={ [ 'card', 'giropay' ] }
				/>
			</FlagsContextWrapper>
		);

		expect(
			screen.queryByText(
				/The selected methods require an additional currency/
			)
		).toBeInTheDocument();
	} );
} );
