/**
 * External dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';

type CurrencyCode = string;

type SelectedCurrencyResponse = {
	isLoading: boolean;
	selectedCurrency?: CurrencyCode;
	setSelectedCurrency: ( selectedCurrency: CurrencyCode ) => void;
};

/**
 * Custom hook for retrieving and updating the selected currency.
 * This is used to determine which currency to display in the overview page.
 * The selected currency is stored in the `wcpay_overview_selected_currency` option.
 *
 * @return {SelectedCurrencyResponse} An object containing the selected currency, a setter function, and a loading state.
 */
export const useSelectedCurrency = (): SelectedCurrencyResponse => {
	const selectedCurrencyOptionName = 'wcpay_overview_selected_currency';
	const { updateOptions } = useDispatch( 'wc/admin/options' );

	const setSelectedCurrency = ( currencyCode: CurrencyCode ) => {
		updateOptions( {
			[ selectedCurrencyOptionName ]: currencyCode,
		} );
	};

	return useSelect( ( select ) => {
		const { getOption, isResolving } = select( 'wc/admin/options' );

		return {
			isLoading: isResolving( 'getOption', [
				selectedCurrencyOptionName,
			] ),
			setSelectedCurrency,
			selectedCurrency: getOption( selectedCurrencyOptionName ),
		};
	} );
};
