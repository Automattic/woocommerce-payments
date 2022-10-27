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

const CaptureAuthorizationButton = ( {
	orderId,
	paymentIntentId,
}: {
	orderId: number;
	paymentIntentId: string;
} ): JSX.Element => {
	const { doCaptureAuthorization, isLoading } = useAuthorization(
		paymentIntentId,
		orderId
	);

	return (
		<Button
			isSecondary
			isSmall
			onClick={ doCaptureAuthorization }
			isBusy={ isLoading }
			disabled={ isLoading }
		>
			{ __( 'Capture', 'woocommerce-payments' ) }
		</Button>
	);
};

export default CaptureAuthorizationButton;
