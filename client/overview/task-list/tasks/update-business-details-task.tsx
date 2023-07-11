/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import type { TaskItemProps } from '../types';
import UpdateBusinessDetailsModal from 'wcpay/overview/modal/update-business-details';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

export const getUpdateBusinessDetailsTask = (
	errorMessages: string[],
	status: string,
	accountLink: string,
	currentDeadline: number | null,
	pastDue: boolean
): TaskItemProps | null => {
	const accountRestrictedSoon = 'restricted_soon' === status;
	const accountDetailsPastDue = 'restricted' === status && pastDue;
	const hasMultipleErrors = 1 < errorMessages.length;
	const hasSingleError = 1 === errorMessages.length;

	let accountDetailsTaskDescription = '',
		errorMessageDescription,
		accountDetailsUpdateByDescription;

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

	const renderModal = () => {
		let container = document.querySelector(
			'#wcpay-update-business-details-container'
		);

		if ( ! container ) {
			container = document.createElement( 'div' );
			container.id = 'wcpay-update-business-details-container';
			document.body.appendChild( container );
		}

		render(
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

	const handleClick = () => {
		if ( 'complete' === status ) {
			return;
		}

		if ( hasMultipleErrors ) {
			renderModal();
		} else {
			window.open( accountLink, '_blank' );
		}
	};

	return {
		key: 'update-business-details',
		level: 1,
		title: sprintf(
			/* translators: %s: WooPayments */
			__( 'Update %s business details', 'woocommerce-payments' ),
			'WooPayments'
		),
		content: accountDetailsTaskDescription,
		completed: 'complete' === status,
		onClick: handleClick,
		action: handleClick,
		actionLabel: hasMultipleErrors
			? __( 'More details', 'woocommerce-payments' )
			: __( 'Update', 'woocommerce-payments' ),
		expandable: true,
		expanded: true,
		showActionButton: true,
	};
};
