/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

/**
 * Internal dependencies.
 */
import wcpayTracks from 'tracks';
import { getAdminUrl } from 'wcpay/utils';
import UpdateBusinessDetailsModal from '../modal/update-business-details';

export const getTasks = ( {
	accountStatus,
	showUpdateDetailsTask,
	wpcomReconnectUrl,
	isAccountOverviewTasksEnabled,
	numDisputesNeedingResponse = 0,
} ) => {
	const renderModal = (
		errorMessages,
		status,
		accountLink,
		currentDeadline
	) => {
		const container = document.createElement( 'div' );
		container.id = 'wcpay-update-business-details-container';
		document.body.appendChild( container );
		ReactDOM.render(
			<UpdateBusinessDetailsModal
				errorMessages={ errorMessages }
				accountStatus={ status }
				accountLink={ accountLink }
				currentDeadline={ currentDeadline }
			/>,
			container
		);
	};

	const getErrorMessagesFromRequirements = ( requirements ) => {
		if ( 'errors' in requirements ) {
			const errorMessages = requirements.errors.map( ( error ) => {
				return error.reason;
			} );
			return [ ...new Set( errorMessages ) ];
		}

		return [];
	};

	const {
		status,
		currentDeadline,
		pastDue,
		accountLink,
		requirements,
	} = accountStatus;
	const accountRestrictedSoon = 'restricted_soon' === status;
	const accountDetailsPastDue = 'restricted' === status && pastDue;
	const errorMessages = getErrorMessagesFromRequirements( requirements );
	let accountDetailsTaskDescription,
		errorMessageDescription,
		accountDetailsUpdateByDescription;

	const isDisputeTaskVisible = 0 < numDisputesNeedingResponse;
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
		// TODO: If more than one error exists, show the modal, if only one error exists, show the error directly to the user here.
		isAccountOverviewTasksEnabled &&
			showUpdateDetailsTask && {
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
