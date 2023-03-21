/**
 * External dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';

type Currency = string;

type SelectedCurrencyResponse = {
	isLoading: boolean;
	selectedCurrency?: Currency;
	setSelectedCurrency: ( selectedCurrency: Currency ) => void;
};

export const useSelectedCurrency = (): SelectedCurrencyResponse => {
	const selectedCurrencyOptionName = 'wcpay_overview_selected_currency';
	const { updateOptions } = useDispatch( 'wc/admin/options' );

	const setSelectedCurrency = ( currency: Currency ) => {
		updateOptions( {
			[ selectedCurrencyOptionName ]: currency,
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
