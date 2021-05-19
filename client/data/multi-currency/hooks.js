/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useAvailableCurrencies = () =>
	useSelect( ( select ) => {
		const { getAvailableCurrencies, isResolving } = select( STORE_NAME );

		return {
			deposit: getAvailableCurrencies(),
			isLoading: isResolving( 'getAvailableCurrencies', [] ),
		};
	}, [] );

export const useEnabledCurrencies = () =>
	useSelect( ( select ) => {
		const { getEnabledCurrencies, isResolving } = select( STORE_NAME );

		return {
			deposit: getEnabledCurrencies(),
			isLoading: isResolving( 'getEnabledCurrencies', [] ),
		};
	}, [] );
