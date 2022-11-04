/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useAuthorization } from 'wcpay/data';

const CaptureAuthorizationButton = ( {
	orderId,
	paymentIntentId,
	buttonIsPrimary = false,
	buttonIsSmall = true,
	onClick = () => undefined,
}: {
	orderId: number;
	paymentIntentId: string;
	buttonIsPrimary?: boolean;
	buttonIsSmall?: boolean;
	onClick?: () => void;
} ): JSX.Element => {
	const { doCaptureAuthorization, isLoading } = useAuthorization(
		paymentIntentId,
		orderId
	);

	// Use local state to prevent the button to be in 'busy' state when it loads
	const [ isCaptureRequested, setIsCaptureRequested ] = useState( false );

	return (
		<Button
			isPrimary={ buttonIsPrimary }
			isSecondary={ ! buttonIsPrimary }
			isSmall={ buttonIsSmall }
			onClick={ () => {
				onClick();
				setIsCaptureRequested( true );
				doCaptureAuthorization();
			} }
			isBusy={ isLoading && isCaptureRequested } // Button should be in busy state when the capture is requested
			disabled={ isLoading && isCaptureRequested } // Button should be disabled when the capture is requested
		>
			{ __( 'Capture', 'woocommerce-payments' ) }
		</Button>
	);
};

export default CaptureAuthorizationButton;
