/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch, dispatch } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useCurrencies = () =>
	useSelect( ( select ) => {
		const { getCurrencies, isResolving } = select( STORE_NAME );

		return {
			currencies: getCurrencies(),
			isLoading: isResolving( 'getCurrencies', [] ),
		};
	}, [] );

export const useAvailableCurrencies = () =>
	useSelect( ( select ) => {
		const { getAvailableCurrencies } = select( STORE_NAME );

		return getAvailableCurrencies();
	} );

export const useEnabledCurrencies = () => {
	const enabledCurrencies = useSelect( ( select ) => {
		const { getEnabledCurrencies } = select( STORE_NAME );

		return getEnabledCurrencies();
	} );
	const { submitEnabledCurrenciesUpdate } = useDispatch( STORE_NAME );
	return {
		enabledCurrencies,
		submitEnabledCurrenciesUpdate,
	};
};

export const useCustomerCurrencies = () => {
	const customerCurrencies = useSelect( ( select ) => {
		const { getCustomerCurrencies } = select( STORE_NAME );

		return getCustomerCurrencies();
	} );
	const { submitCustomerCurrenciesUpdate } = useDispatch( STORE_NAME );
	return {
		customerCurrencies,
		submitCustomerCurrenciesUpdate,
	};
};

export const useStoreSettings = () => {
	const { storeSettings, isLoading } = useSelect( ( select ) => {
		const { getStoreSettings, isResolving } = select( STORE_NAME );

		return {
			storeSettings: getStoreSettings(),
			isLoading: isResolving( 'getStoreSettings', [] ),
		};
	} );
	const { submitStoreSettingsUpdate } = useDispatch( STORE_NAME );
	return { storeSettings, isLoading, submitStoreSettingsUpdate };
};

export const useDefaultCurrency = () =>
	useSelect( ( select ) => {
		const { getDefaultCurrency } = select( STORE_NAME );

		return getDefaultCurrency();
	} );

export const useCurrencySettings = ( currencyCode ) => {
	const { currencySettings, isLoading } = useSelect( ( select ) => {
		const { getCurrencySettings, isResolving } = select( STORE_NAME );

		return {
			currencySettings: getCurrencySettings( currencyCode ),
			isLoading: isResolving( 'getCurrencySettings', [ currencyCode ] ),
		};
	} );
	const { submitCurrencySettings } = dispatch( STORE_NAME );
	return { currencySettings, isLoading, submitCurrencySettings };
};
