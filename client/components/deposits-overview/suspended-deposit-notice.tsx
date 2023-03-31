/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import BannerNotice from 'components/banner-notice';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';

/**
 * Renders the deposit schedule section.
 *
 * @return {JSX.Element} Rendered element with the deposit schedule section.
 */
function SuspendedDepositNotice(): JSX.Element {
	return (
		<BannerNotice
			className="wcpay-deposits-overview__suspended-notice"
			icon={ <NoticeOutlineIcon /> }
			isDismissible={ false }
			status="warning"
		>
			{ interpolateComponents( {
				mixedString: __(
					'Your deposits are {{strong}}temporarily suspended{{/strong}}. {{suspendLink}}Learn more{{/suspendLink}}',
					'woocommerce-payments'
				),
				components: {
					strong: <strong />,
					suspendLink: (
						<Link
							href={
								'https://woocommerce.com/document/payments/faq/deposits-suspended/'
							}
						/>
					),
				},
			} ) }
		</BannerNotice>
	);
}

export default SuspendedDepositNotice;
