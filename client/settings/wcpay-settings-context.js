/**
 * External dependencies
 */
import { createContext } from 'react';

const WCPaySettingsContext = createContext( {
	accountFees: {},
	accountLoans: {},
	accountStatus: {},
	featureFlags: {},
} );

export default WCPaySettingsContext;
