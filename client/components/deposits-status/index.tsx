/** @format */

/**
 * External dependencies
 */
import GridiconCheckmarkCircle from 'gridicons/dist/checkmark-circle';
import GridiconNotice from 'gridicons/dist/notice';
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import React from 'react';

/**
 * Internal dependencies
 */
import 'components/account-status/shared.scss';
import type { AccountStatus } from 'wcpay/types/account/account-status';

type DepositsStatus = 'enabled' | 'disabled' | 'blocked';
type DepositsIntervals = 'daily' | 'weekly' | 'monthly' | 'manual';

interface Props {
	status: DepositsStatus;
	interval: DepositsIntervals;
	accountStatus: AccountStatus;
	poEnabled: boolean;
	poComplete: boolean;
	iconSize: number;
}

const DepositsStatus: React.FC< Props > = ( {
	status,
	interval,
	accountStatus,
	poEnabled,
	poComplete,
	iconSize,
} ) => {
	let className = 'account-status__info__green';
	let description;
	let icon = <GridiconCheckmarkCircle size={ iconSize } />;
	const automaticIntervals: DepositsIntervals[] = [
		'daily',
		'weekly',
		'monthly',
	];
	const showSuspendedNotice = 'blocked' === status;

	if ( 'pending_verification' === accountStatus ) {
		description = __( 'Pending verification', 'woocommerce-payments' );
		className = 'account-status__info__gray';
		icon = <GridiconNotice size={ iconSize } />;
	} else if ( 'disabled' === status ) {
		description =
			poEnabled && ! poComplete
				? __( 'Not connected', 'woocommerce-payments' )
				: __( 'Disabled', 'woocommerce-payments' );
		className =
			poEnabled && ! poComplete
				? 'account-status__info__gray'
				: 'account-status__info__red';
		icon = <GridiconNotice size={ iconSize } />;
	} else if ( showSuspendedNotice ) {
		const learnMoreHref =
			'https://woocommerce.com/document/woopayments/deposits/why-deposits-suspended/';
		description = createInterpolateElement(
			/* translators: <a> - suspended accounts FAQ URL */
			__(
				'Temporarily suspended (<a>learn more</a>)',
				'woocommerce-payments'
			),
			{
				a: (
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						href={ learnMoreHref }
						target="_blank"
						rel="noopener noreferrer"
					/>
				),
			}
		);
		className = 'account-status__info__yellow';
		icon = <GridiconNotice size={ iconSize } />;
	} else if ( automaticIntervals.includes( interval ) ) {
		description = __( 'Automatic', 'woocommerce-payments' );
	} else if ( 'manual' === interval ) {
		description = __( 'Manual', 'woocommerce-payments' );
	} else {
		description = __( 'Unknown', 'woocommerce-payments' );
	}

	return (
		<span className={ className }>
			{ icon }
			{ description }
		</span>
	);
};

export default DepositsStatus;
