export const getFingerprint = async () => {
	const FingerprintJS = await import(
		/* webpackChunkName: "fp_agent" */
		'@fingerprintjs/fingerprintjs'
	);

	const agent = await FingerprintJS.load( { monitoring: false } );

	if ( ! agent ) {
		throw new Error( 'Unable to load agent.' );
	}

	return await agent.get();
};
