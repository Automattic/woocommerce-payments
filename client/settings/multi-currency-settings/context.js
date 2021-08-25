/**
 * External dependencies
 */
import { createContext } from 'react';

const WcPayMultiCurrencyContext = createContext( {
	isAutomaticSwitchEnabled: true,
	setIsAutomaticSwitchEnabled: () => null,
	willAddCurrencySelectorToCartWidget: true,
	setWillAddCurrencySelectorToCartWidget: () => null,
	status: 'resolved',
} );

export default WcPayMultiCurrencyContext;
