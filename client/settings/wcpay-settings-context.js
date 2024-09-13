/**
 * External dependencies
 */
import { createContext } from 'react';

const WCPaySettingsContext = createContext( {
	accountFees: {},
	accountLoans: {},
	accountStatus: {},
	featureFlags: {
		isAuthAndCaptureEnabled: false,
		isDisputeIssuerEvidenceEnabled: false,
		woopay: false,
		isStripeEceEnabled: false,
	},
	isDirty: false,
	setisDirty: () => null,
} );

export default WCPaySettingsContext;
