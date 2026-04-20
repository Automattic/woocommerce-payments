/**
 * External dependencies
 */
import { createContext, Dispatch, SetStateAction } from 'react';
import { ProtectionSettingsUI } from '../interfaces';

const FraudPreventionSettingsContext = createContext< {
	protectionSettingsUI: ProtectionSettingsUI;
	setProtectionSettingsUI: Dispatch< SetStateAction< ProtectionSettingsUI > >;
	setIsDirty: Dispatch< SetStateAction< boolean > >;
} >( {
	protectionSettingsUI: {},
	setProtectionSettingsUI: () => null,
	setIsDirty: () => null,
} );

export default FraudPreventionSettingsContext;
