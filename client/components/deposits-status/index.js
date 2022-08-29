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

const DepositsStatus = ( props ) => {
	const { depositsStatus, iconSize } = props;
	let className = 'account-status__info__green';
	let description;
	let icon = <GridiconCheckmarkCircle size={ iconSize } />;
	const automaticStatuses = [ 'daily', 'weekly', 'monthly' ];

	if ( automaticStatuses.includes( depositsStatus ) ) {
		description = __( 'Automatic', 'woocommerce-payments' );
	} else if ( 'disabled' === depositsStatus ) {
		description = __( 'Disabled', 'woocommerce-payments' );
		className = 'account-status__info__red';
		icon = <GridiconNotice size={ iconSize } />;
	} else if ( 'manual' === depositsStatus ) {
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
