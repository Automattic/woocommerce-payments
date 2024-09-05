/**
 * External dependencies
 */
import { createContext } from 'react';

/**
 * Internal dependencies
 */
import { useSettings, useMultiCurrency } from 'multi-currency/interface/data';
import {
	WCPaySettingsContext,
	WizardTaskContext,
} from 'multi-currency/interface/functions';
import { paymentMethodsMap } from 'multi-currency/interface/assets';

/**
 * WooPaymentsContext API.
 */
export const WooPaymentsContext = createContext( {
	// Assets
	paymentMethodsMap: () => {},
	// Data
	useSettings: () => {},
	useMultiCurrency: () => {},
	// Contexts
	WCPaySettingsContext: () => {},
	WizardTaskContext: () => {},
} );

/**
 * V1 WooPaymentsContext API implementation.
 *
 * @param {Object} props Incoming props.
 * @return {ReactNode} WooPaymentsContext provider.
 */
export const WooPaymentsContextV1 = ( { children } ) => {
	const value = {
		// Assets
		paymentMethodsMap: paymentMethodsMap,
		// Data
		useSettings: useSettings,
		useMultiCurrency: useMultiCurrency,
		// Contexts
		WCPaySettingsContext: WCPaySettingsContext,
		WizardTaskContext: WizardTaskContext,
	};

	return (
		<WooPaymentsContext.Provider value={ value }>
			{ children }
		</WooPaymentsContext.Provider>
	);
};
