/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import { useSelectedCurrency } from 'overview/hooks';
import { getCurrency } from 'utils/currency';
import InlineLabelSelect from '../inline-label-select';
import { recordEvent } from 'tracks';

/**
 * Returns an select option object for a currency select control.
 */
const getCurrencyOption = (
	currency: string
): {
	name: string;
	key: string;
} => {
	const { code, symbol } = getCurrency( currency )?.getCurrencyConfig() || {};
	const currencySymbolDecoded = decodeEntities( symbol || '' );

	if (
		// Show just the currency the currency code is used as the name, e.g. 'EUR'
		// if no currency config is found,
		! code ||
		! symbol ||
		// or if the symbol is identical to the currency code, e.g. 'CHF CHF'.
		currencySymbolDecoded === code
	) {
		return {
			name: currency.toUpperCase(),
			key: currency,
		};
	}
	return {
		// A rendered name of the currency with symbol, e.g. `EUR €`.
		name: `${ code } ${ currencySymbolDecoded }`,
		key: currency,
	};
};

/**
 * Custom hook to get the selected currency from the URL query parameter 'selected_currency'.
 * If no currency is selected, the store's default currency will be selected.
 */
const useSelectedCurrencyWithDefault = ( depositCurrencies: string[] ) => {
	const { selectedCurrency, setSelectedCurrency } = useSelectedCurrency();

	useEffect( () => {
		// The selected currency is invalid if:
		// * no currency is explicitly selected via URL query, or
		// * no currency is found for the provided query parameter.
		const isSelectedCurrencyValid =
			selectedCurrency &&
			depositCurrencies.find(
				( currency ) =>
					currency.toLowerCase() === selectedCurrency.toLowerCase()
			);

		// Select the store's default currency if the selected currency is invalid.
		if ( ! isSelectedCurrencyValid && depositCurrencies.length > 0 ) {
			setSelectedCurrency( depositCurrencies[ 0 ].toLowerCase() );
		}
	}, [ depositCurrencies, selectedCurrency, setSelectedCurrency ] );

	return { selectedCurrency, setSelectedCurrency };
};

/**
 * Renders a currency select input used for the Payments Overview page.
 * Should only be rendered if there are multiple deposit currencies available.
 */
export const CurrencySelect: React.FC< {
	/** An array of available deposit currencies, e.g. ['usd', 'eur']. */
	depositCurrencies: string[];
} > = ( { depositCurrencies } ) => {
	const currencyOptions = depositCurrencies.map( getCurrencyOption );
	const {
		selectedCurrency,
		setSelectedCurrency,
	} = useSelectedCurrencyWithDefault( depositCurrencies );

	return (
		<InlineLabelSelect
			label="Currency"
			value={ currencyOptions.find(
				( option ) => option.key === selectedCurrency
			) }
			options={ currencyOptions }
			onChange={ ( { selectedItem } ) => {
				if ( ! selectedItem ) {
					return;
				}

				const currencyCode = selectedItem.key.toLowerCase();
				setSelectedCurrency( currencyCode );
				recordEvent( 'wcpay_overview_currency_select_change', {
					selected_currency: currencyCode,
				} );
			} }
		/>
	);
};
