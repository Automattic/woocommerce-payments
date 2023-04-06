/**
 * External dependencies
 */
import { getQuery, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { useAllDepositsOverviews } from 'wcpay/data';

// Extend the Query interface to include the selected_currency query parameter.
declare module '@woocommerce/navigation' {
	interface Query {
		selected_currency?: string;
	}
}

type CurrencyCode = string;

type UseSelectedCurrencyResult = {
	selectedCurrency?: CurrencyCode;
	setSelectedCurrency: ( selectedCurrency: CurrencyCode ) => void;
};

/**
 * Custom hook for retrieving and updating the selected currency.
 * This is used to determine which currency to display in the overview page.
 * The selected currency is set as a 'selected_currency' query parameter in the URL.
 *
 * @return {UseSelectedCurrencyResult} An object containing the selected currency and a setter function.
 */
export const useSelectedCurrency = (): UseSelectedCurrencyResult => {
	const setSelectedCurrency = ( currencyCode: CurrencyCode ) => {
		updateQueryString( {
			selected_currency: currencyCode,
		} );
	};

	const selectedCurrency = getQuery().selected_currency;

	return {
		setSelectedCurrency,
		selectedCurrency,
	};
};

type SelectedCurrencyOverview = {
	account?: AccountOverview.Account;
	overview?: AccountOverview.Overview;
	isLoading: boolean;
};
/**
 * A custom hook to get the selected currency overview.
 * If the selected currency is not valid, the first currency overview is returned.
 * If the selected currency is valid, the overview for that currency is returned.
 *
 * @return {SelectedCurrencyOverview} An object containing the account and the overview for the selected currency.
 */
export const useSelectedCurrencyOverview = (): SelectedCurrencyOverview => {
	const {
		overviews,
		isLoading: isAccountOverviewsLoading,
	} = useAllDepositsOverviews();
	const { currencies, account } = overviews;

	const { selectedCurrency } = useSelectedCurrency();

	const isSelectedCurrencyValid = currencies.some(
		( currency ) => currency.currency === selectedCurrency
	);

	const overview = isSelectedCurrencyValid
		? currencies.find(
				( currency ) => currency.currency === selectedCurrency
		  )
		: currencies[ 0 ];

	return {
		account,
		overview,
		isLoading: isAccountOverviewsLoading,
	};
};
