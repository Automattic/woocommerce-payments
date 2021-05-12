/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

const { accountStatus } = wcpaySettings;
const { status, currentDeadline, pastDue, accountLink } = accountStatus;
const accountRestrictedSoon = 'restricted_soon' === status;
const accountDetailsPastDue = 'restricted' === status && pastDue;
let accountDetailsTaskDescription;

if ( accountRestrictedSoon ) {
	accountDetailsTaskDescription = sprintf(
		/* translators: %s - formatted requirements current deadline (date) */
		__(
			'Update by %s to avoid a disruption in deposits.',
			'woocommerce-payments'
		),
		dateI18n( 'ga M j, Y', moment( currentDeadline * 1000 ).toISOString() )
	);
} else if ( accountDetailsPastDue ) {
	accountDetailsTaskDescription =
		/* translators: <a> - dashboard login URL */
		__(
			'Payments and deposits are disabled for this account until missing business information is updated.',
			'woocommerce-payments'
		);
}
export const tasks = [
	( accountRestrictedSoon || accountDetailsPastDue ) && {
		key: 'update-business-details',
		level: 1,
		title: __(
			'Update WooCommerce Payments business details',
			'woocommerce-payments'
		),
		content: accountDetailsTaskDescription,
		completed: false,
		onClick: () => {
			window.open( accountLink, '_blank' );
		},
	},
].filter( Boolean );
