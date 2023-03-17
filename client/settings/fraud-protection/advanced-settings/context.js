/**
 * External dependencies
 */
import { createContext } from 'react';

const FraudPreventionSettingsContext = createContext( {
	advancedFraudProtectionSettings: false,
	setAdvancedFraudProtectionSettings: () => {},
} );

export default FraudPreventionSettingsContext;
