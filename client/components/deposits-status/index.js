/** @format */

/**
 * External dependencies
 */
import GridiconCheckmarkCircle from 'gridicons/dist/checkmark-circle';
import GridiconNotice from 'gridicons/dist/notice';
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import 'components/account-status/shared.scss';

const DepositsStatus = ( { status, interval, iconSize } ) => {
	const isCustomDepositSchedulesEnabled =
		window.wcpaySettings?.featureFlags?.customDepositSchedules;
	let className = 'account-status__info__green';
	let description;
	let icon = <GridiconCheckmarkCircle size={ iconSize } />;
	const automaticStatuses = [ 'daily', 'weekly', 'monthly' ];
	const showSuspendedNotice =
		( ! isCustomDepositSchedulesEnabled && 'manual' === interval ) ||
		'blocked' === status;

	if ( 'disabled' === status ) {
		description = __( 'Disabled', 'woocommerce-payments' );
		className = 'account-status__info__red';
		icon = <GridiconNotice size={ iconSize } />;
	} else if ( showSuspendedNotice ) {
		const learnMoreHref =
			'https://woocommerce.com/document/payments/faq/deposits-suspended/';
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
	} else if ( automaticStatuses.includes( interval ) ) {
		description = __( 'Automatic', 'woocommerce-payments' );
	} else if ( isCustomDepositSchedulesEnabled && 'manual' === interval ) {
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
