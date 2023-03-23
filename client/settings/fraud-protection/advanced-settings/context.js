/**
 * External dependencies
 */
import { createContext } from 'react';

const FraudPreventionSettingsContext = createContext( {
	advancedFraudProtectionSettings: false,
	setAdvancedFraudProtectionSettings: () => {},
	protectionSettingsChanged: false,
	setProtectionSettingsChanged: () => {},
} );

export default FraudPreventionSettingsContext;
