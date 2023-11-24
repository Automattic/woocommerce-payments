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

interface CaptureAuthorizationButtonProps {
	orderId: number;
	paymentIntentId: string;
	buttonIsPrimary?: boolean;
	buttonIsSmall?: boolean;
	onClick?: () => void;
}

const CaptureAuthorizationButton: React.FC< CaptureAuthorizationButtonProps > = ( {
	orderId,
	children,
	paymentIntentId,
	buttonIsPrimary = false,
	buttonIsSmall = true,
	onClick = () => undefined,
} ) => {
	const {
		isLoading,
		isRequesting,
		doCaptureAuthorization,
	} = useAuthorization( paymentIntentId, orderId );

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
			disabled={ ( isLoading && isCaptureRequested ) || isRequesting } // Button should be disabled when the capture is requested
		>
			{ children || __( 'Capture', 'woocommerce-payments' ) }
		</Button>
	);
};

export default CaptureAuthorizationButton;
