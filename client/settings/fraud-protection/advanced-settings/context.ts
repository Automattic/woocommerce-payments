/**
 * External dependencies
 */
import { createContext } from 'react';
import { FraudPreventionSettingsContextType } from '../interfaces';

const FraudPreventionSettingsContext = createContext( {
	protectionSettingsUI: {},
	setProtectionSettingsUI: () => null,
	protectionSettingsChanged: false,
	setProtectionSettingsChanged: () => false,
	setIsDirty: () => null,
} as FraudPreventionSettingsContextType );

export default FraudPreventionSettingsContext;
