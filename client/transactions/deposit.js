/** @format **/

/**
 * External dependencies
 */

import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import { addQueryArgs } from '@wordpress/url';
import { Link } from '@woocommerce/components';

const Deposit = ( { depositId, dateAvailable } ) => {
	const depositUrl = addQueryArgs(
		'admin.php',
		{
			page: 'wc-admin',
			path: '/payments/deposits/details',
			id: depositId,
		}
	);

	const formattedDateAvailable = dateAvailable != null && (
		dateI18n( 'M j, Y', moment.utc( dateAvailable ) )
	);

	return depositId ? (
		<Link href={ depositUrl }>
			{ formattedDateAvailable || __( 'Deposit', 'woocommerce-payments' ) }
		</Link>
	) : ( formattedDateAvailable || __( 'Pending', 'woocommerce-payments' ) );
};

export default Deposit;
