/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import moment from 'moment';
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import { ExternalLink } from '@wordpress/components';
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
	if ( depositId && dateAvailable ) {
		const depositUrl = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/payouts/details',
			id: depositId,
		} );

		const formattedDateAvailable = dateI18n(
			'M j, Y',
			moment.utc( dateAvailable ).toISOString(),
			true // TODO Change call to gmdateI18n and remove this deprecated param once WP 5.4 support ends.
		);

		return <Link href={ depositUrl }>{ formattedDateAvailable }</Link>;
	}

	// Show an icon with a tooltip to communicate that the payout will be available in the future.
	return (
		<>
			{ __( 'Future payout', 'woocommerce-payments' ) }
			<ClickTooltip
				content={ interpolateComponents( {
					mixedString: __(
						'This transaction will be included in an upcoming automated payout. The date of the payout will be displayed here once it is scheduled. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
						'woocommerce-payments'
					),
					components: {
						learnMoreLink: (
							<ExternalLink href="https://woocommerce.com/document/woopayments/payouts/payout-schedule/#pending-funds" />
						),
					},
				} ) }
				buttonIcon={ <InfoOutlineIcon /> }
			/>
		</>
	);
};

export default Deposit;
