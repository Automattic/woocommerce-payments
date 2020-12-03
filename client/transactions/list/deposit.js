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
	const depositUrl = addQueryArgs( 'admin.php', {
		page: 'wc-admin',
		path: '/payments/deposits/details',
		id: depositId,
	} );

	const formattedDateAvailable =
		dateAvailable != null &&
		// Do not localize because it is intended as a date only, without time information.
		dateI18n(
			'M j, Y',
			moment.utc( dateAvailable ).toISOString(),
			true // TODO Change call to gmdateI18n and remove this deprecated param once WP 5.4 support ends.
		);

	return depositId ? (
		<Link href={ depositUrl }>
			{ formattedDateAvailable ||
				__( 'Deposit details', 'woocommerce-payments' ) }
		</Link>
	) : (
		formattedDateAvailable || __( 'Pending', 'woocommerce-payments' )
	);
};

export default Deposit;
