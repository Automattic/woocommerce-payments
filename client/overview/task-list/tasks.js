/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

/**
 * Internal dependencies.
 */
import createAdditionalMethodsSetupTask from '../../additional-methods-setup/task';
import createMultiCurrencySetupTask from '../../multi-currency-setup/task';
import { formatCurrency } from 'utils/currency';
import { getDetailsURL } from 'components/details-link';

export const getTasks = ( {
	accountStatus,
	showUpdateDetailsTask,
	additionalMethodsSetup = {},
	multiCurrencySetup = {},
	wpcomReconnectUrl,
	isAccountOverviewTasksEnabled,
	needsHttpsSetup,
	disputes,
} ) => {
	const { status, currentDeadline, pastDue, accountLink } = accountStatus;
	const accountRestrictedSoon = 'restricted_soon' === status;
	const accountDetailsPastDue = 'restricted' === status && pastDue;
	let accountDetailsTaskDescription;

	const getDisputesTasks = () => {
		if ( ! disputes ) {
			return [];
		}
		return disputes.map(
			( { amount, currency, evidence_details: evidenceDetails, id } ) => {
				return {
					key: `dispute-resolution-${ id }`,
					level: 3,
					title: sprintf(
						/* translators: %s - amount referred to in the dispute */
						__(
							'A disputed payment for %s needs your response',
							'woocommerce-payments'
						),
						formatCurrency( amount || 0, currency || 'USD' )
					),
					content: sprintf(
						/* translators: %s - deadline to respond (date) */
						__( 'Respond by %s', 'woocommerce-payments' ),
						dateI18n(
							'M j, Y - g:iA',
							moment(
								evidenceDetails.due_by * 1000
							).toISOString()
						)
					),
					completed: false,
					onClick: () => {
						window.location.href = getDetailsURL( id, 'disputes' );
					},
				};
			}
		);
	};

	const disputesToResolve = getDisputesTasks();

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
				additionalInfo: accountDetailsTaskDescription,
				completed: 'complete' === status,
				onClick:
					'complete' === status
						? undefined
						: () => {
								window.open( accountLink, '_blank' );
						  },
				visible: true,
				type: 'extension',
			},
		isAccountOverviewTasksEnabled &&
			wpcomReconnectUrl && {
				key: 'reconnect-wpcom-user',
				level: 1,
				title: __(
					'Reconnect WooCommerce Payments',
					'woocommerce-payments'
				),
				content: __(
					'WooCommerce Payments is missing a connected WordPress.com account. ' +
						'Some functionality will be limited without a connected account.',
					'woocommerce-payments'
				),
				completed: false,
				onClick: () => {
					window.location.href = wpcomReconnectUrl;
				},
			},
		isAccountOverviewTasksEnabled &&
			needsHttpsSetup && {
				key: 'force-secure-checkout',
				title: __( 'Force secure checkout', 'woocommerce-payments' ),
				content: __(
					'Protect your customers data and increase trustworthiness of your store by forcing HTTPS on checkout pages.',
					'woocommerce-payments'
				),
				completed: false,
				onClick: () => {
					window.open(
						'https://docs.woocommerce.com/document/ssl-and-https/#section-7',
						'_blank'
					);
				},
				actionLabel: __( 'Read more', 'woocommerce-payments' ),
			},
		additionalMethodsSetup.isTaskVisible &&
			createAdditionalMethodsSetupTask( additionalMethodsSetup ),
		multiCurrencySetup.isTaskVisible &&
			createMultiCurrencySetupTask( multiCurrencySetup ),
		...disputesToResolve,
	].filter( Boolean );
};
