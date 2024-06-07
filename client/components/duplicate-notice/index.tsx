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
	dismissedNotices: { [ key: string ]: string[] };
	setDismissedDuplicateNotices: ( notices: {
		[ key: string ]: string[];
	} ) => null;
}

function DuplicateNotice( {
	paymentMethod,
	gatewaysEnablingPaymentMethod,
	dismissedNotices,
	setDismissedDuplicateNotices,
}: DuplicateNoticeProps ): JSX.Element | null {
	const { updateOptions } = useDispatch( 'wc/admin/options' );

	const handleDismiss = useCallback( () => {
		const updatedDismissedNotices = { ...dismissedNotices };
		if ( updatedDismissedNotices[ paymentMethod ] ) {
			// If there are existing dismissed notices for the payment method, append to the current array.
			updatedDismissedNotices[ paymentMethod ] = [
				...new Set( [
					...updatedDismissedNotices[ paymentMethod ],
					...gatewaysEnablingPaymentMethod,
				] ),
			];
		} else {
			updatedDismissedNotices[
				paymentMethod
			] = gatewaysEnablingPaymentMethod;
		}

		setDismissedDuplicateNotices( updatedDismissedNotices );
		updateOptions( {
			wcpay_duplicate_payment_method_notices_dismissed: updatedDismissedNotices,
		} );
		wcpaySettings.dismissedDuplicateNotices = updatedDismissedNotices;
	}, [
		paymentMethod,
		gatewaysEnablingPaymentMethod,
		dismissedNotices,
		setDismissedDuplicateNotices,
		updateOptions,
	] );

	if ( dismissedNotices?.[ paymentMethod ] ) {
		const isNoticeDismissedForEveryGateway = gatewaysEnablingPaymentMethod.every(
			( value ) => dismissedNotices[ paymentMethod ].includes( value )
		);

		if ( isNoticeDismissedForEveryGateway ) {
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
