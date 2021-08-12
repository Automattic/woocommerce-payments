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

jest.mock( '@wordpress/a11y', () => ( {
	...jest.requireActual( '@wordpress/a11y' ),
	speak: jest.fn(),
} ) );

describe( 'CurrencyInformationForMethods', () => {
	beforeEach( () => {
		useCurrencies.mockReturnValue( {
			isLoading: false,
			currencies: {
				available: {
					EUR: { name: 'Euro', symbol: '€' },
					USD: { name: 'US Dollar', symbol: '$' },
					PLN: { name: 'Polish złoty', symbol: 'zł' },
				},
			},
		} );
		useEnabledCurrencies.mockReturnValue( {
			enabledCurrencies: {
				USD: { id: 'usd', code: 'USD' },
			},
		} );
	} );

	it( 'should not display content when the feature flag is disabled', () => {
		const { container } = render(
			<CurrencyInformationForMethods
				selectedMethods={ [
					'card',
					'bancontact',
					'giropay',
					'ideal',
					'p24',
				] }
			/>
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'should not display content when the currency data is being loaded', () => {
		useCurrencies.mockReturnValue( {
			isLoading: true,
		} );
		const { container } = render(
			<CurrencyInformationForMethods
				selectedMethods={ [
					'card',
					'bancontact',
					'giropay',
					'ideal',
					'p24',
				] }
			/>
		);

		expect(
			screen.queryByText( /requires an additional currency/ )
		).not.toBeInTheDocument();
		expect( container.firstChild ).toBeNull();
	} );

	it( 'should not display content when the enabled currencies contains Euros', () => {
		useEnabledCurrencies.mockReturnValue( {
			enabledCurrencies: {
				USD: { id: 'usd', code: 'USD' },
				EUR: { id: 'eur', code: 'EUR' },
				PLN: { id: 'pln', code: 'PLN' },
			},
		} );
		const { container } = render(
			<CurrencyInformationForMethods
				selectedMethods={ [
					'giropay',
					'bancontact',
					'card',
					'ideal',
					'p24',
				] }
			/>
		);

		expect(
			screen.queryByText( /requires an additional currency/ )
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
			screen.queryByText( /requires an additional currency/ )
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
			screen.queryByText( /we\'ll add Euro \(€\) to your store/ )
		).toBeInTheDocument();
	} );

	it( 'should display a notice when one of the enabled methods is both EUR and PLN method', () => {
		render(
			<CurrencyInformationForMethods
				selectedMethods={ [ 'card', 'p24' ] }
			/>
		);

		expect(
			screen.queryByText(
				/(we\'ll add|and) Euro \(€\) (and|to your store)/
			)
		).toBeInTheDocument();

		expect(
			screen.queryByText(
				/(we\'ll add|and) Polish złoty \(zł\) (and|to your store)/
			)
		).toBeInTheDocument();
	} );
} );
