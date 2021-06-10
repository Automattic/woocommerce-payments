/**
 * External dependencies
 */
import { createContext } from 'react';

const WCPaySettingsContext = createContext( {
	accountStatus: {},
	featureFlags: {},
} );

export default WCPaySettingsContext;
