/**
 * External dependencies
 */
import { createContext } from 'react';

const FraudPreventionSettingsContext = createContext( {
	protectionSettingsUI: false,
	setProtectionSettingsUI: () => {},
	protectionSettingsChanged: false,
	setProtectionSettingsChanged: () => {},
} );

export default FraudPreventionSettingsContext;
