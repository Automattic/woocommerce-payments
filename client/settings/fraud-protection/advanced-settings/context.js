/**
 * External dependencies
 */
import { createContext } from 'react';

const FraudPreventionSettingsContext = createContext( {
	protectionSettingsUI: false,
	setProtectionSettingsUI: () => {},
} );

export default FraudPreventionSettingsContext;
