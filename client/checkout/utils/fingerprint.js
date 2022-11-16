/**
 * External dependencies
 */
import FingerprintJS from '@fingerprintjs/fingerprintjs';

export const getFingerprint = async () => {
	const agent = await FingerprintJS.load( { monitoring: false } );

	// Throw an error if agent is not available
	if ( ! agent ) {
		throw new Error( 'Unable to load agent.' );
	}

	return await agent.get();
};
