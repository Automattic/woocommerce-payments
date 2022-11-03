/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useAuthorization } from 'wcpay/data';
import wcpayTracks from 'tracks';

const CaptureAuthorizationButton = ( {
	orderId,
	paymentIntentId,
	buttonIsPrimary = false,
	buttonIsSmall = true,
}: {
	orderId: number;
	paymentIntentId: string;
	buttonIsPrimary?: boolean;
	buttonIsSmall?: boolean;
} ): JSX.Element => {
	const { doCaptureAuthorization, isLoading } = useAuthorization(
		paymentIntentId,
		orderId
	);

	return (
		<Button
			isPrimary={ buttonIsPrimary }
			isSecondary={ ! buttonIsPrimary }
			isSmall={ buttonIsSmall }
			onClick={ () => {
				wcpayTracks.recordEvent(
					'payments_transactions_details_capture_charge_button_click',
					{
						payment_intent_id: paymentIntentId,
					}
				);
				doCaptureAuthorization();
			} }
			isBusy={ isLoading }
			disabled={ isLoading }
		>
			{ __( 'Capture', 'woocommerce-payments' ) }
		</Button>
	);
};

export default CaptureAuthorizationButton;
