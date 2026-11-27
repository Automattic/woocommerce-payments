/**
 * External dependencies
 */
import qit from '@qit/helpers';

/**
 * Legacy E2E toggles these states on the WooPayments Dev Tools settings page. QIT cannot
 * install Dev Tools, so `bootstrap/qit-dev-tools-equivalents.php` copies the Dev Tools hooks
 * and these helpers do what a settings save does: write the option and, where the next account
 * fetch matters, refresh the account.
 */

const cardTestingProtectionOption =
	'wcpaydev_force_card_testing_protection_on';
const forceDisconnectedOption = 'wcpaydev_force_disconnected';

const refreshAccountData = async () => {
	await qit.wp(
		"eval 'WC_Payments::get_account_service()->refresh_account_data();'",
		true
	);
};

export const enableCardTestingProtection = async () => {
	await qit.wp( `option update ${ cardTestingProtectionOption } 1`, true );
	await refreshAccountData();
};

export const disableCardTestingProtection = async () => {
	await qit.wp( `option update ${ cardTestingProtectionOption } 0`, true );
	await refreshAccountData();
};

const rateLimiterOption =
	'wcpay_session_rate_limiter_disabled_wcpay_card_declined_registry';

export const disableFailedTransactionRateLimiter = async () => {
	await qit.wp( `option set ${ rateLimiterOption } yes` );
};

/**
 * Makes WooPayments act as disconnected from the Transact Platform Server.
 * The mu-plugin empties the account cache on every request while the option is set.
 */
export const enableActAsDisconnectedFromWCPay = async () => {
	await qit.wp( `option update ${ forceDisconnectedOption } 1` );
};

/**
 * Reconnects WooPayments to the Transact Platform Server.
 */
export const disableActAsDisconnectedFromWCPay = async () => {
	await qit.wp( `option update ${ forceDisconnectedOption } 0` );
	await refreshAccountData();
};
