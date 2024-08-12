/**
 * External dependencies
 */
import React, { useCallback } from 'react';
import InlineNotice from '../inline-notice';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';
import { getAdminUrl } from 'wcpay/utils';
import { useDispatch } from '@wordpress/data';

export type PaymentMethodToPluginsMap = { [ key: string ]: string[] };
interface DuplicateNoticeProps {
	paymentMethod: string;
	gatewaysEnablingPaymentMethod: string[];
	dismissedNotices: PaymentMethodToPluginsMap;
	setDismissedDuplicateNotices: (
		notices: PaymentMethodToPluginsMap
	) => null;
}

function DuplicateNotice( {
	paymentMethod,
	gatewaysEnablingPaymentMethod,
	dismissedNotices,
	setDismissedDuplicateNotices,
}: DuplicateNoticeProps ): JSX.Element | null {
	const { updateOptions } = useDispatch( 'wc/admin/options' );

	const handleDismiss = useCallback( () => {
		const updatedNotices = { ...dismissedNotices };
		if ( updatedNotices[ paymentMethod ] ) {
			// If there are existing dismissed notices for the payment method, append to the current array.
			updatedNotices[ paymentMethod ] = [
				...new Set( [
					...updatedNotices[ paymentMethod ],
					...gatewaysEnablingPaymentMethod,
				] ),
			];
		} else {
			updatedNotices[ paymentMethod ] = gatewaysEnablingPaymentMethod;
		}

		setDismissedDuplicateNotices( updatedNotices );
		updateOptions( {
			wcpay_duplicate_payment_method_notices_dismissed: updatedNotices,
		} );
		wcpaySettings.dismissedDuplicateNotices = updatedNotices;
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
