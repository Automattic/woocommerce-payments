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
import {
	AccountDetailsData,
	BannerBackgroundColor,
} from 'wcpay/types/account/account-details';
import { getIconByName } from './utils';

interface BannerProps {
	banner: AccountDetailsData[ 'banner' ];
}

const getNoticeStatusFromColor = ( color: BannerBackgroundColor ) => {
	switch ( color ) {
		case 'green':
			return 'success';
		case 'blue':
			return 'info';
		case 'yellow':
			return 'warning';
		case 'red':
			return 'error';
		default:
			return 'info';
	}
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
			<>
				{ banner.text }
				{ banner.cta_text && banner.cta_link && (
					<>
						{ ' ' }
						<ExternalLink href={ banner.cta_link }>
							{ banner.cta_text }
						</ExternalLink>
					</>
				) }
			</>
		</InlineNotice>
	);
};

export default Banner;
