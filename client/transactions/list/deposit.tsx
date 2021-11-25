/** @format **/

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { Link } from '@woocommerce/components';
import { __ } from '@wordpress/i18n';
import React from 'react';
import { getAdminUrl } from 'wcpay/utils';

interface DepositProps {
	depositId?: string;
	dateAvailable?: string;
}

const Deposit = ( { depositId, dateAvailable }: DepositProps ): JSX.Element => {
	if ( depositId && dateAvailable ) {
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

		const estimated = depositId.includes( 'wcpay_estimated_' )
			? __( 'Estimated', 'woocommerce-payments' )
			: '';

		return (
			<Link href={ depositUrl }>
				{ estimated } { formattedDateAvailable }
			</Link>
		);
	}

	return <></>;
};

export default Deposit;
