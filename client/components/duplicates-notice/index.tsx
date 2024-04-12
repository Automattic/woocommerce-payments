/**
 * External dependencies
 */
import React, { useCallback } from 'react';
import InlineNotice from '../inline-notice';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';
import { getAdminUrl } from 'wcpay/utils';
import { useDispatch } from '@wordpress/data';

interface DuplicatesNoticeProps {
	paymentMethod: string;
	dismissedNotices: string[];
	setDismissedNotices: ( notices: string[] ) => void;
}

function DuplicatesNotice( {
	paymentMethod,
	dismissedNotices,
	setDismissedNotices,
}: DuplicatesNoticeProps ): JSX.Element | null {
	const { updateOptions } = useDispatch( 'wc/admin/options' );

	const handleDismissNotice = useCallback( () => {
		const updatedNotices = [ ...dismissedNotices, paymentMethod ];
		setDismissedNotices( updatedNotices );
		updateOptions( {
			wcpay_duplicate_payment_method_notices_dismissed: updatedNotices,
		} );
	}, [
		paymentMethod,
		dismissedNotices,
		setDismissedNotices,
		updateOptions,
	] );

	if ( dismissedNotices.includes( paymentMethod ) ) {
		return null;
	}

	return (
		<InlineNotice
			status="warning"
			icon={ true }
			isDismissible={ true }
			onRemove={ handleDismissNotice }
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

export default DuplicatesNotice;
