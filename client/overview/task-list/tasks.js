/** @format **/

/**
 * External dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import { addQueryArgs } from '@wordpress/url';
import moment from 'moment';

/**
 * Internal dependencies.
 */
import wcpayTracks from 'tracks';
import { getAdminUrl } from 'wcpay/utils';

export const getTasks = ( {
	accountStatus,
	showUpdateDetailsTask,
	wpcomReconnectUrl,
	isAccountOverviewTasksEnabled,
	numDisputesNeedingResponse = 0,
} ) => {
	const {
		status,
		currentDeadline,
		pastDue,
		accountLink,
		isProgressivelyOnboarded,
	} = accountStatus;
	const accountRestrictedSoon = 'restricted_soon' === status;
	const accountDetailsPastDue = 'restricted' === status && pastDue;
	let accountDetailsTaskDescription;

	const isDisputeTaskVisible = 0 < numDisputesNeedingResponse;

	if ( accountRestrictedSoon ) {
		accountDetailsTaskDescription = sprintf(
			/* translators: %s - formatted requirements current deadline (date) */
			__(
				'Update by %s to avoid a disruption in deposits.',
				'woocommerce-payments'
			),
			dateI18n(
				'ga M j, Y',
				moment( currentDeadline * 1000 ).toISOString()
			)
		);
	} else if ( accountDetailsPastDue ) {
		accountDetailsTaskDescription =
			/* translators: <a> - dashboard login URL */
			__(
				'Payments and deposits are disabled for this account until missing business information is updated.',
				'woocommerce-payments'
			);
	}

	return [
		isAccountOverviewTasksEnabled &&
			'yes' === showUpdateDetailsTask && {
				key: 'update-business-details',
				level: 1,
				title: __(
					'Update WooCommerce Payments business details',
					'woocommerce-payments'
				),
				content: accountDetailsTaskDescription,
				completed: 'complete' === status,
				onClick:
					'complete' === status
						? undefined
						: () => {
								if ( isProgressivelyOnboarded ) {
									const collectPayoutRequirementsLink = addQueryArgs(
										wcpaySettings.connectUrl,
										{
											collect_payout_requirements: true,
										}
									);
									window.location = collectPayoutRequirementsLink;
								} else {
									window.open( accountLink, '_blank' );
								}
						  },
				actionLabel: __( 'Update', 'woocommerce-payments' ),
				visible: true,
				type: 'extension',
				expandable: true,
				expanded: true,
				showActionButton: true,
			},
		isAccountOverviewTasksEnabled &&
			wpcomReconnectUrl && {
				key: 'reconnect-wpcom-user',
				level: 1,
				title: __(
					'Reconnect WooCommerce Payments',
					'woocommerce-payments'
				),
				additionalInfo: __(
					'WooCommerce Payments is missing a connected WordPress.com account. ' +
						'Some functionality will be limited without a connected account.',
					'woocommerce-payments'
				),
				completed: false,
				onClick: () => {
					window.location.href = wpcomReconnectUrl;
				},
				actionLabel: __( 'Reconnect', 'woocommerce-payments' ),
				expandable: true,
				expanded: true,
				showActionButton: true,
			},
		isDisputeTaskVisible && {
			key: 'dispute-resolution-task',
			level: 3,
			title: sprintf(
				_n(
					'1 disputed payment needs your response',
					'%s disputed payments need your response',
					numDisputesNeedingResponse,
					'woocommerce-payments'
				),
				numDisputesNeedingResponse
			),
			additionalInfo: __( 'View and respond', 'woocommerce-payments' ),
			completed: false,
			isDeletable: true,
			isDismissable: true,
			allowSnooze: true,
			onClick: () => {
				wcpayTracks.recordEvent( 'wcpay_overview_task', {
					task: 'dispute-resolution-task',
				} );
				window.location.href = getAdminUrl( {
					page: 'wc-admin',
					path: '/payments/disputes',
					filter: 'awaiting_response',
				} );
			},
		},
	].filter( Boolean );
};

export const taskSort = ( a, b ) => {
	if ( a.completed || b.completed ) {
		return a.completed ? 1 : -1;
	}
	// Three is the lowest level.
	const aLevel = a.level || 3;
	const bLevel = b.level || 3;
	if ( aLevel === bLevel ) {
		return 0;
	}
	return aLevel > bLevel ? 1 : -1;
};
