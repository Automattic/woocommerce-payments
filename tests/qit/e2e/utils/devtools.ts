/**
 * External dependencies
 */
import qit from '/qitHelpers';

/**
 * The legacy E2E environment relied on the WooPayments Dev Tools plugin UI to toggle
 * options like card testing protection. The QIT stack does not load that plugin, so
 * these helpers mirror the behaviour by patching the relevant options directly via WP-CLI.
 */

const setCardTestingProtection = async ( enabled: boolean ) => {
	const { stdout } = await qit.wp(
		'option get wcpay_account_data --format=json',
		true
	);
	let cache: Record< string, unknown > = {};
	try {
		cache = stdout.trim().length ? JSON.parse( stdout ) : {};
	} catch ( error ) {
		cache = {};
	}
	const data = {
		...( typeof cache.data === 'object' && cache.data !== null
			? cache.data
			: {} ),
		card_testing_protection_eligible: enabled,
	};
	const updatedCache = {
		...cache,
		data,
		fetched: Math.floor( Date.now() / 1000 ),
		errored: false,
	};
	const payload = JSON.stringify( updatedCache );
	const escapedPayload = payload.replace( /'/g, `'"'"'` );
	await qit.wp(
		`option update wcpay_account_data '${ escapedPayload }' --format=json`,
		true
	);
	await qit.wp(
		`option update wcpaydev_force_card_testing_protection_on ${
			enabled ? 1 : 0
		}`,
		true
	);
	await qit
		.wp( 'cache delete wcpay_account_data options', true )
		.catch( () => undefined );
};

export const enableCardTestingProtection = async () => {
	await setCardTestingProtection( true );
};

export const disableCardTestingProtection = async () => {
	await setCardTestingProtection( false );
};

const rateLimiterOption =
	'wcpay_session_rate_limiter_disabled_wcpay_card_declined_registry';

export const disableFailedTransactionRateLimiter = async () => {
	await qit.wp( `option set ${ rateLimiterOption } yes`, true );
};
