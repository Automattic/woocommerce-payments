/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { useAccountDomesticCurrency } from '../../../data';
import {
	useCurrencies,
	useEnabledCurrencies,
} from 'multi-currency/interface/data';
import CurrencyInformationForMethods from '../currency-information-for-methods';
import WCPaySettingsContext from '../../../settings/wcpay-settings-context';

jest.mock( '../../../data', () => ( {
	useAccountDomesticCurrency: jest.fn(),
} ) );

jest.mock( 'multi-currency/interface/data', () => ( {
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
		useAccountDomesticCurrency.mockReturnValue( 'usd' );
	} );

	it( 'should not display content when the feature flag is disabled', () => {
		const { container } = render(
			<FlagsContextWrapper multiCurrency={ false }>
				<CurrencyInformationForMethods
					selectedMethods={ [
						'card',
						'bancontact',
						'eps',
						'giropay',
						'ideal',
						'p24',
						'sofort',
						'sepa_debit',
					] }
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
					selectedMethods={ [
						'card',
						'bancontact',
						'eps',
						'giropay',
						'ideal',
						'p24',
						'sofort',
						'sepa_debit',
					] }
				/>
			</FlagsContextWrapper>
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
			<FlagsContextWrapper>
				<CurrencyInformationForMethods
					selectedMethods={ [
						'card',
						'bancontact',
						'eps',
						'giropay',
						'ideal',
						'p24',
						'sofort',
						'sepa_debit',
					] }
				/>
			</FlagsContextWrapper>
		);

		expect(
			screen.queryByText( /requires an additional currency/ )
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
			screen.queryByText( /requires an additional currency/ )
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
			screen.queryByText( /we\'ll add Euro \(€\) to your store/, {
				ignore: '.a11y-speak-region',
			} )
		).toBeInTheDocument();
	} );

	it( 'should display a notice when one of the enabled methods is both EUR and PLN method', () => {
		render(
			<FlagsContextWrapper>
				<CurrencyInformationForMethods
					selectedMethods={ [ 'card', 'p24' ] }
				/>
			</FlagsContextWrapper>
		);

		expect(
			screen.queryByText(
				/(we\'ll add|and) Euro \(€\) (and|to your store)/,
				{
					ignore: '.a11y-speak-region',
				}
			)
		).toBeInTheDocument();

		expect(
			screen.queryByText(
				/(we\'ll add|and) Polish złoty \(zł\) (and|to your store)/,
				{
					ignore: '.a11y-speak-region',
				}
			)
		).toBeInTheDocument();
	} );

	it( "should not display a notice for additional currencies for BNPL methods, if the account's currency is already enabled", () => {
		const { container } = render(
			<FlagsContextWrapper>
				<CurrencyInformationForMethods
					selectedMethods={ [
						'afterpay_clearpay',
						'klarna',
						'affirm',
					] }
				/>
			</FlagsContextWrapper>
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( "should display a notice to enable additional currencies for BNPL methods, if the account' currency is not enabled", () => {
		useAccountDomesticCurrency.mockReturnValue( 'eur' );
		render(
			<FlagsContextWrapper>
				<CurrencyInformationForMethods
					selectedMethods={ [
						'afterpay_clearpay',
						'klarna',
						'affirm',
					] }
				/>
			</FlagsContextWrapper>
		);

		expect(
			screen.queryByText(
				/Afterpay, Klarna, and Affirm require an additional currency/,
				{
					ignore: '.a11y-speak-region',
				}
			)
		).toBeInTheDocument();
		expect(
			screen.queryByText( /we\'ll add Euro \(€\) to your store/, {
				ignore: '.a11y-speak-region',
			} )
		).toBeInTheDocument();
	} );
} );
