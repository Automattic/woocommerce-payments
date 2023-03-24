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

interface CancelAuthorizationButtonProps {
	orderId: number;
	paymentIntentId: string;
	isDestructive?: boolean;
	isSmall?: boolean;
	onClick?: () => void;
}

const CancelAuthorizationButton: React.FC< CancelAuthorizationButtonProps > = ( {
	orderId,
	children,
	paymentIntentId,
	isDestructive = true,
	isSmall = false,
	onClick = () => undefined,
} ) => {
	const { doCancelAuthorization, isLoading, isRequesting } = useAuthorization(
		paymentIntentId,
		orderId
	);

	// Use local state to prevent the button to be in 'busy' state when it loads
	const [ IsCancelRequested, setIsCancelRequested ] = useState( false );

	return (
		<Button
			isDestructive={ isDestructive }
			isSmall={ isSmall }
			onClick={ () => {
				onClick();
				setIsCancelRequested( true );
				doCancelAuthorization();
			} }
			isBusy={ isLoading && IsCancelRequested } // Button should be in busy state when the cancel is requested
			disabled={ ( isLoading && IsCancelRequested ) || isRequesting } // Button should be disabled when the cancel is requested
		>
			{ children || __( 'Cancel', 'woocommerce-payments' ) }
		</Button>
	);
};

export default CancelAuthorizationButton;
