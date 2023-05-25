/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */

import { useDisputes } from 'wcpay/data';
import BannerNotice from '../banner-notice';
import { getDisputesNoticeString } from 'wcpay/disputes/utils';

const ActiveDisputesNotice: React.FC = () => {
	const { disputes: activeDisputes, isLoading } = useDisputes( {
		filter: 'awaiting_response',
	} );

	if ( ! activeDisputes || isLoading ) {
		return null;
	}

	const disputesNoticeString = getDisputesNoticeString( {
		activeDisputes,
	} );

	if ( ! disputesNoticeString ) {
		return null;
	}

	return <BannerNotice>{ disputesNoticeString }</BannerNotice>;
};

export default ActiveDisputesNotice;
