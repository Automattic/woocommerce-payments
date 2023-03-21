/**
 * External dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';
import { useAllDepositsOverviews } from 'wcpay/data';

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

type SelectedCurrencyOverviewResponse = {
	account?: AccountOverview.Account;
	overview?: AccountOverview.Overview;
	isLoading: boolean;
};
/**
 * A custom hook to get the selected currency overview.
 * If the selected currency is not valid, the first currency overview is returned.
 * If the selected currency is valid, the overview for that currency is returned.
 *
 * @return {SelectedCurrencyOverviewResponse} An object containing the account and the overview for the selected currency.
 */
export const useSelectedCurrencyOverview = (): SelectedCurrencyOverviewResponse => {
	const {
		overviews,
		isLoading: isAccountOverviewsLoading,
	} = useAllDepositsOverviews() as AccountOverview.OverviewsResponse;
	const { currencies, account } = overviews;

	const {
		selectedCurrency,
		isLoading: isSelectedCurrencyLoading,
	} = useSelectedCurrency();

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
		isLoading: isAccountOverviewsLoading || isSelectedCurrencyLoading,
	};
};
