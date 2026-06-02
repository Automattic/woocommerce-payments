/**
 * External dependencies
 */
import type { ReactElement } from 'react';
import { createElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';

/**
 * Internal dependencies
 */
import type { TaskItemProps } from '../types';
import { LazyUpdateBusinessDetailsTaskDescription } from './lazy-update-business-details-task-description';
import { runUpdateBusinessDetailsTaskAction } from './update-business-details-task-action-loader';

interface RequirementError {
	code: string;
	reason: string;
}

const getUpdateBusinessDetailsTask = (
	requirementErrors: RequirementError[],
	status: string,
	accountLink: string,
	currentDeadline: number | null,
	pastDue: boolean,
	detailsSubmitted: boolean
): TaskItemProps | null => {
	const accountRestrictedSoon = status === 'restricted_soon';
	const accountDetailsPastDue = status === 'restricted' && pastDue;
	const hasMultipleErrors = requirementErrors.length > 1;
	const hasSingleError = requirementErrors.length === 1;
	let accountDetailsTaskDescription: ReactElement | string = '',
		accountDetailsUpdateByDescription;

	if ( accountRestrictedSoon && currentDeadline ) {
		accountDetailsUpdateByDescription = sprintf(
			/* translators: %s - formatted requirements current deadline (date) */
			__(
				'Update by %s to avoid a disruption in payouts.',
				'woocommerce-payments'
			),
			dateI18n(
				'ga M j, Y',
				new Date( currentDeadline * 1000 ).toISOString()
			)
		);

		if ( hasSingleError ) {
			accountDetailsTaskDescription = createElement(
				LazyUpdateBusinessDetailsTaskDescription,
				{
					error: requirementErrors[ 0 ],
					updateByDescription: accountDetailsUpdateByDescription,
				}
			);
		} else {
			accountDetailsTaskDescription = accountDetailsUpdateByDescription;
		}
	} else if ( accountDetailsPastDue ) {
		if ( hasSingleError ) {
			accountDetailsTaskDescription = createElement(
				LazyUpdateBusinessDetailsTaskDescription,
				{
					error: requirementErrors[ 0 ],
				}
			);
		} else if ( ! detailsSubmitted ) {
			accountDetailsTaskDescription =
				/* translators: <a> - dashboard login URL */
				__(
					'Payments and payouts are disabled for this account until setup is completed.',
					'woocommerce-payments'
				);
		} else {
			accountDetailsTaskDescription =
				/* translators: <a> - dashboard login URL */
				__(
					'Payments and payouts are disabled for this account until missing business information is updated.',
					'woocommerce-payments'
				);
		}
	}

	const handleClick = () => {
		runUpdateBusinessDetailsTaskAction( {
			status,
			hasMultipleErrors,
			detailsSubmitted,
			accountLink,
			requirementErrors,
			currentDeadline,
		} );
	};

	let actionLabel;

	if ( hasMultipleErrors ) {
		actionLabel = __( 'More details', 'woocommerce-payments' );
	} else if ( ! detailsSubmitted ) {
		actionLabel = __( 'Finish setup', 'woocommerce-payments' );
	} else {
		actionLabel = __( 'Update', 'woocommerce-payments' );
	}

	return {
		key: ! detailsSubmitted ? 'complete-setup' : 'update-business-details',
		level: 1,
		title: ! detailsSubmitted
			? sprintf(
					/* translators: %s: WooPayments */
					__( 'Finish setting up %s', 'woocommerce-payments' ),
					'WooPayments'
			  )
			: sprintf(
					/* translators: %s: WooPayments */
					__( 'Update %s business details', 'woocommerce-payments' ),
					'WooPayments'
			  ),
		content: accountDetailsTaskDescription,
		completed: status === 'complete' || status === 'enabled',
		onClick: handleClick,
		action: handleClick,
		actionLabel: actionLabel,
		expandable: true,
		expanded: true,
		showActionButton: true,
	};
};

export default getUpdateBusinessDetailsTask;
