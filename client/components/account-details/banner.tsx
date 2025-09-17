/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { ExternalLink } from '@wordpress/components';

/**
 * Internal dependencies
 */
import InlineNotice from 'wcpay/components/inline-notice';
import { AccountDetailsData } from 'wcpay/types/account/account-details';
import { getIconByName } from './utils';

interface BannerProps {
	banner: AccountDetailsData[ 'banner' ];
}

const getNoticeStatusFromColor = ( color: 'yellow' | 'red' ) => {
	return color === 'yellow' ? 'warning' : 'error';
};

const Banner: React.FC< BannerProps > = ( { banner } ) => {
	if ( ! banner ) {
		return null;
	}

	return (
		<InlineNotice
			status={ getNoticeStatusFromColor( banner.background_color ) }
			icon={ getIconByName( banner.icon ) }
			className="woopayments-account-details__banner"
			isDismissible={ false }
		>
			<div>
				{ banner.text }
				{ banner.cta_text && banner.cta_link && (
					<>
						{ ' ' }
						<ExternalLink href={ banner.cta_link }>
							{ banner.cta_text }
						</ExternalLink>
					</>
				) }
			</div>
		</InlineNotice>
	);
};

export default Banner;
