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
	gatewaysEnablingPaymentMethod: string[];
	dismissedDuplicateNotices: string[];
	setDismissedDuplicateNotices: ( notices: {
		[ key: string ]: string[];
	} ) => null;
}

function DuplicateNotice( {
	paymentMethod,
	gatewaysEnablingPaymentMethod,
	dismissedDuplicateNotices,
	setDismissedDuplicateNotices,
}: DuplicateNoticeProps ): JSX.Element | null {
	const { updateOptions } = useDispatch( 'wc/admin/options' );

	const handleDismiss = useCallback( () => {
		let updatedNotices = dismissedDuplicateNotices;
		if ( updatedNotices ) {
			// If there are existing dismissedDuplicateNotices for the payment method, append to the current array.
			updatedNotices = [
				...new Set( [
					...updatedNotices,
					...gatewaysEnablingPaymentMethod,
				] ),
			];
		} else {
			updatedNotices = gatewaysEnablingPaymentMethod;
		}

		setDismissedDuplicateNotices( {
			[ paymentMethod ]: updatedNotices,
		} );
		updateOptions( {
			wcpay_duplicate_payment_method_notices_dismissed: {
				[ paymentMethod ]: updatedNotices,
			},
		} );
		wcpaySettings.dismissedDuplicateNotices = {
			[ paymentMethod ]: updatedNotices,
		};
	}, [
		paymentMethod,
		gatewaysEnablingPaymentMethod,
		dismissedDuplicateNotices,
		setDismissedDuplicateNotices,
		updateOptions,
	] );

	if ( dismissedDuplicateNotices?.length > 0 ) {
		const isDismissed =
			dismissedDuplicateNotices &&
			gatewaysEnablingPaymentMethod.every( ( value ) =>
				dismissedDuplicateNotices.includes( value )
			);

		if ( isDismissed ) {
			return null;
		}
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
