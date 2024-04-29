/**
 * External dependencies
 */
import React, { useCallback } from 'react';
import InlineNotice from '../inline-notice';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';
import { getAdminUrl } from 'wcpay/utils';
import { useDispatch } from '@wordpress/data';

interface DuplicateNoticeProps {
	paymentMethod: string;
	dismissedDuplicateNotices: string[];
	setDismissedDuplicateNotices: ( notices: string[] ) => void;
}

function DuplicateNotice( {
	paymentMethod,
	dismissedDuplicateNotices,
	setDismissedDuplicateNotices,
}: DuplicateNoticeProps ): JSX.Element | null {
	const { updateOptions } = useDispatch( 'wc/admin/options' );

	const handleDismiss = useCallback( () => {
		const updatedNotices = [ ...dismissedDuplicateNotices, paymentMethod ];
		setDismissedDuplicateNotices( updatedNotices );
		updateOptions( {
			wcpay_duplicate_payment_method_notices_dismissed: updatedNotices,
		} );
		wcpaySettings.dismissedDuplicateNotices = updatedNotices;
	}, [
		paymentMethod,
		dismissedDuplicateNotices,
		setDismissedDuplicateNotices,
		updateOptions,
	] );

	if ( dismissedDuplicateNotices.includes( paymentMethod ) ) {
		return null;
	}

	return (
		<InlineNotice
			status="warning"
			icon={ true }
			isDismissible={ true }
			onRemove={ handleDismiss }
		>
			{ interpolateComponents( {
				mixedString: __(
					'This payment method is enabled by other extensions. {{reviewExtensions}}Review extensions{{/reviewExtensions}} to improve the shopper experience.',
					'woocommerce-payments'
				),
				components: {
					reviewExtensions: (
						<a
							href={ getAdminUrl( {
								page: 'wc-settings',
								tab: 'checkout',
							} ) }
						>
							Review extensions
						</a>
					),
				},
			} ) }
		</InlineNotice>
	);
}

export default DuplicateNotice;
