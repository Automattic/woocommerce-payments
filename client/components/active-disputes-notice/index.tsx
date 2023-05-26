/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { TaskItem } from '@woocommerce/experimental';

/**
 * Internal dependencies
 */

import { useDisputes } from 'wcpay/data';
import { getDisputesNoticeString } from 'wcpay/disputes/utils';
import { getAdminUrl } from 'wcpay/utils';
import './style.scss';

const ActiveDisputesNotice: React.FC = () => {
	const { disputes: activeDisputes, isLoading } = useDisputes( {
		filter: 'awaiting_response',
	} );

	if ( ! activeDisputes || isLoading ) {
		return null;
	}

	const disputesNoticeString = getDisputesNoticeString( activeDisputes );

	if ( ! disputesNoticeString ) {
		return null;
	}

	const handleClick = () => {
		window.location.href = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/disputes',
			filter: 'awaiting_response',
		} );
	};

	return (
		<div className="wcpay-active-disputes-notice">
			<TaskItem
				title={ disputesNoticeString }
				expanded
				completed={ false }
				content={ '' } // TODO: add subtitle here
				level={ 2 } // TODO: dynamic level dispute deadline is approaching
				showActionButton
				actionLabel={ __( 'Respond now', 'woocommerce-payments' ) }
				onClick={ handleClick }
				action={ handleClick }
			/>
		</div>
	);
};

export default ActiveDisputesNotice;
