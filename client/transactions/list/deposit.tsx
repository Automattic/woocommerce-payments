/** @format **/

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { Link } from '@woocommerce/components';
import React from 'react';
import { getAdminUrl } from 'wcpay/utils';

interface DepositProps {
	depositId?: string;
	dateAvailable?: string;
}

const Deposit = ( { depositId, dateAvailable }: DepositProps ): JSX.Element => {
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

	return <>Future deposit</>;
};

export default Deposit;
