/**
 * External dependencies
 */
import { createContext } from 'react';

const AdditionalMethodsSetupContext = createContext( {
	setSetupCompleted: () => null,
	setUpeEnabled: () => null,
} );

export default AdditionalMethodsSetupContext;
