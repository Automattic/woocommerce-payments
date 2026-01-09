/**
 * External dependencies
 */
import qit from '@qit/helpers';

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
	await qit.wp( `option set ${ rateLimiterOption } yes` );
};

/**
 * Forces WooPayments to act as disconnected from the Transact Platform Server.
 * This mirrors enabling the "Act as disconnected from WCPay" dev tools option.
 */
export const enableActAsDisconnectedFromWCPay = async () => {
	// Force disconnect by setting options directly via WP-CLI
	await qit.wp( 'option update wcpaydev_force_disconnected "1"' );
	await qit.wp( 'option update wcpay_account_data "[]"' );

	// Clear caches to ensure the change takes effect
	await qit.wp( 'cache flush' );
	await qit.wp( 'transient delete --all' );
};

/**
 * Re-enables connection to the WooPayments Transact Platform Server.
 * This mirrors disabling the "Act as disconnected from WCPay" dev tools option.
 */
export const disableActAsDisconnectedFromWCPay = async () => {
	// Re-enable connection by removing force disconnected flag
	await qit.wp( 'option delete wcpaydev_force_disconnected' );

	// Clear the account data cache so it refreshes from server
	await qit.wp( 'option delete wcpay_account_data' );

	// Clear all caches and transients to force refresh
	await qit.wp( 'cache flush' );
	await qit.wp( 'transient delete --all' );
};
