/**
 * External dependencies
 */
import { useContext } from 'react';

/**
 * Internal dependencies
 */
import WcPayMultiCurrencyContext from './context';

const useIsAutomaticSwitchEnabled = () => {
	const {
		isAutomaticSwitchEnabled,
		setIsAutomaticSwitchEnabled,
	} = useContext( WcPayMultiCurrencyContext );

	return [ isAutomaticSwitchEnabled, setIsAutomaticSwitchEnabled ];
};

const useWillAddCurrencySwitcherToCartWidget = () => {
	const {
		willAddCurrencySwitcherToCartWidget,
		setWillAddCurrencySwitcherToCartWidget,
	} = useContext( WcPayMultiCurrencyContext );

	return [
		willAddCurrencySwitcherToCartWidget,
		setWillAddCurrencySwitcherToCartWidget,
	];
};

export default {
	useIsAutomaticSwitchEnabled,
	useWillAddCurrencySwitcherToCartWidget,
};
