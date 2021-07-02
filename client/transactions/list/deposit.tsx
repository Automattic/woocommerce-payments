/** @format **/

/**
 * External dependencies
 */

import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { addQueryArgs } from '@wordpress/url';
import { Link } from '@woocommerce/components';
import { __ } from '@wordpress/i18n';
import React from 'react';

interface DepositProps {
	depositId: string;
	dateAvailable: string | null;
}

const Deposit: React.FunctionComponent< DepositProps > = ( {
	depositId,
	dateAvailable,
} ): React.ReactElement => {
	const depositUrl = addQueryArgs( 'admin.php', {
		page: 'wc-admin',
		path: '/payments/deposits/details',
		id: depositId,
	} );

	const formattedDateAvailable =
		null != dateAvailable &&
		// Do not localize because it is intended as a date only, without time information.
		dateI18n(
			'M j, Y',
			moment.utc( dateAvailable ).toISOString(),
			true // TODO Change call to gmdateI18n and remove this deprecated param once WP 5.4 support ends.
		);

	const estimated =
		depositId && depositId.includes( 'wcpay_estimated_' )
			? __( 'Estimated', 'woocommerce-payments' )
			: '';

	if ( depositId && dateAvailable ) {
		return (
			<Link href={ depositUrl }>
				{ estimated } { formattedDateAvailable }
			</Link>
		);
	}

	return <></>;
};

export default Deposit;
