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
	id,
	orderId,
	paymentIntentId,
	buttonIsPrimary = false,
}: {
	id: string;
	orderId: number;
	paymentIntentId: string;
	buttonIsPrimary?: boolean;
} ): JSX.Element => {
	const { doCaptureAuthorization, isLoading } = useAuthorization(
		id,
		orderId,
		paymentIntentId
	);

	return (
		<Button
			isPrimary={ buttonIsPrimary }
			isSecondary={ ! buttonIsPrimary }
			isSmall
			onClick={ doCaptureAuthorization }
			isBusy={ isLoading }
		>
			{ __( 'Capture', 'woocommerce-payments' ) }
		</Button>
	);
};

export default CaptureAuthorizationButton;
