/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import moment from 'moment';
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import { Link } from '@woocommerce/components';
import InfoOutlineIcon from 'gridicons/dist/info-outline';

/**
 * Internal dependencies
 */
import { getAdminUrl } from 'utils';
import { ClickTooltip } from 'components/tooltip';

interface DepositProps {
	depositId?: string;
	dateAvailable?: string;
}

const Deposit: React.FC< DepositProps > = ( { depositId, dateAvailable } ) => {
	if (
		depositId &&
		dateAvailable &&
		! depositId.includes( 'wcpay_estimated_' )
	) {
		const depositUrl = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/deposits/details',
			id: depositId,
		} );

		const formattedDateAvailable = dateI18n(
			'M j, Y',
			moment.utc( dateAvailable ).toISOString(),
			true // TODO Change call to gmdateI18n and remove this deprecated param once WP 5.4 support ends.
		);

		return <Link href={ depositUrl }>{ formattedDateAvailable }</Link>;
	}

	// Show an icon with a tooltip to communicate that the deposit will be available in the future.
	return (
		<ClickTooltip
			content={ __(
				'This transaction will be included in a future deposit. Once created, the estimated deposit date will be shown here.',
				'woocommerce-payments'
			) }
			buttonIcon={ <InfoOutlineIcon /> }
		/>
	);
};

export default Deposit;
