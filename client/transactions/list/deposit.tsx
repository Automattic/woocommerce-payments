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
import { formatDateTime } from 'wcpay/utils/date-time';

interface DepositProps {
	depositId?: string;
	dateAvailable?: string;
}

const Deposit: React.FC< DepositProps > = ( { depositId, dateAvailable } ) => {
	if ( depositId && dateAvailable ) {
		const depositUrl = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/deposits/details',
			id: depositId,
		} );

		const formattedDateAvailable = formatDateTime(
			moment.utc( dateAvailable ).toISOString(),
			{
				includeTime: false,
				useGmt: true,
			}
		);
		return <Link href={ depositUrl }>{ formattedDateAvailable }</Link>;
	}

	// Show an icon with a tooltip to communicate that the deposit will be available in the future.
	return (
		<>
			{ __( 'Future deposit', 'woocommerce-payments' ) }
			<ClickTooltip
				content={ interpolateComponents( {
					mixedString: __(
						'This transaction will be included in an upcoming automated deposit. The date of the deposit will be displayed here once it is scheduled. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
						'woocommerce-payments'
					),
					components: {
						learnMoreLink: (
							<ExternalLink href="https://woocommerce.com/document/woopayments/deposits/deposit-schedule/#pending-funds" />
						),
					},
				} ) }
				buttonIcon={ <InfoOutlineIcon /> }
			/>
		</>
	);
};

export default Deposit;
