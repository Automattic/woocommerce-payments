/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

/**
 * Internal dependencies.
 */
import strings from './strings';
import UpdateBusinessDetailsModal from '../modal/update-business-details';
import { getVerifyBankAccountTask } from './po-tasks';
import {
	getDisputeResolutionTask,
	getDisputesDueWithinDays,
} from './dispute-tasks';

const renderModal = ( errorMessages, status, accountLink, currentDeadline ) => {
	let container = document.querySelector(
		'#wcpay-update-business-details-container'
	);

	if ( ! container ) {
		container = document.createElement( 'div' );
		container.id = 'wcpay-update-business-details-container';
		document.body.appendChild( container );
	}

	ReactDOM.render(
		<UpdateBusinessDetailsModal
			key={ Date.now() }
			errorMessages={ errorMessages }
			accountStatus={ status }
			accountLink={ accountLink }
			currentDeadline={ currentDeadline }
		/>,
		container
	);
};

// Requirements we don't want to show to the user because they are too generic/not useful. These refer to Stripe error codes.
const requirementBlacklist = [ 'invalid_value_other' ];

const getErrorMessagesFromRequirements = ( requirements ) => [
	...new Set(
		requirements?.errors
			?.filter(
				( error ) => ! requirementBlacklist.includes( error.code )
			)
			?.map( ( error ) =>
				error.code in strings.errors
					? strings.errors[ error.code ]
					: error.reason
			)
	),
];

export const getTasks = ( {
	accountStatus,
	showUpdateDetailsTask,
	wpcomReconnectUrl,
	isAccountOverviewTasksEnabled,
	activeDisputes,
} ) => {
	const {
		status,
		currentDeadline,
		pastDue,
		accountLink,
		requirements,
		progressiveOnboarding,
	} = accountStatus;
	const isPoEnabled = progressiveOnboarding?.isEnabled;
	const accountRestrictedSoon = 'restricted_soon' === status;
	const accountDetailsPastDue = 'restricted' === status && pastDue;
	const errorMessages = getErrorMessagesFromRequirements( requirements );
	let accountDetailsTaskDescription,
		errorMessageDescription,
		accountDetailsUpdateByDescription;

	const isDisputeTaskVisible =
		!! activeDisputes &&
		// Only show the dispute task if there are disputes due within 7 days.
		0 < getDisputesDueWithinDays( activeDisputes, 7 ).length;

	const hasMultipleErrors = 1 < errorMessages.length;
	const hasSingleError = 1 === errorMessages.length;

	if ( accountRestrictedSoon && currentDeadline ) {
		accountDetailsUpdateByDescription = sprintf(
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

		if ( hasSingleError ) {
			errorMessageDescription = errorMessages[ 0 ];
			accountDetailsTaskDescription = errorMessageDescription.concat(
				' ',
				accountDetailsUpdateByDescription
			);
		} else {
			accountDetailsTaskDescription = accountDetailsUpdateByDescription;
		}
	} else if ( accountDetailsPastDue ) {
		if ( hasSingleError ) {
			accountDetailsTaskDescription = errorMessages[ 0 ];
		} else {
			accountDetailsTaskDescription =
				/* translators: <a> - dashboard login URL */
				__(
					'Payments and deposits are disabled for this account until missing business information is updated.',
					'woocommerce-payments'
				);
		}
	}

	return [
		isAccountOverviewTasksEnabled &&
			showUpdateDetailsTask &&
			! isPoEnabled && {
				key: 'update-business-details',
				level: 1,
				title: __(
					'Update WooPayments business details',
					'woocommerce-payments'
				),
				content: accountDetailsTaskDescription,
				completed: 'complete' === status,
				onClick:
					'complete' === status
						? undefined
						: () => {
								if ( hasMultipleErrors ) {
									renderModal(
										errorMessages,
										status,
										accountLink,
										currentDeadline
									);
								} else {
									window.open( accountLink, '_blank' );
								}
						  },
				actionLabel: hasMultipleErrors
					? __( 'More details', 'woocommerce-payments' )
					: __( 'Update', 'woocommerce-payments' ),
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
				title: __( 'Reconnect WooPayments', 'woocommerce-payments' ),
				additionalInfo: __(
					'WooPayments is missing a connected WordPress.com account. ' +
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
		isDisputeTaskVisible && getDisputeResolutionTask( activeDisputes ),
		isPoEnabled && getVerifyBankAccountTask(),
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
