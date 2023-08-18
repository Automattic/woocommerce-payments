/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Modal, TextControl, Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useAuthorization } from 'wcpay/data';
import { formatCurrency } from 'wcpay/utils/currency';

interface CaptureAuthorizationButtonProps {
	orderId: number;
	paymentIntentId: string;
	buttonIsPrimary?: boolean;
	buttonIsSmall?: boolean;
	onClick?: () => void;
	capturableAmount: number;
}

const CaptureAuthorizationButton: React.FC< CaptureAuthorizationButtonProps > = ( {
	orderId,
	children,
	paymentIntentId,
	buttonIsPrimary = false,
	buttonIsSmall = true,
	onClick = () => undefined,
	capturableAmount,
} ) => {
	// Use local state to prevent the button to be in 'busy' state when it loads
	const [ isCaptureRequested, setIsCaptureRequested ] = useState( false );
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ amount, setAmount ] = useState( capturableAmount / 100 );
	const [ error, setError ] = useState< string | null >( null );

	const {
		isLoading,
		isRequesting,
		doCaptureAuthorization,
	} = useAuthorization(
		paymentIntentId,
		orderId,
		Math.round( amount * 100 )
	);
	const handleCapture = () => {
		// Validate the entered amount.
		if ( amount > capturableAmount || amount <= 0 ) {
			setError(
				__(
					'The entered amount is invalid. It should be more than zero and less than or equal to the capturable amount.',
					'woocommerce-payments'
				)
			);
			return;
		}
		onClick();
		setIsCaptureRequested( true );
		doCaptureAuthorization(); // Consider passing the `amount` to this function if needed.
		setIsModalOpen( false ); // Close the modal after capturing.
	};

	return (
		<>
			<Button
				isPrimary={ buttonIsPrimary }
				isSecondary={ ! buttonIsPrimary }
				isSmall={ buttonIsSmall }
				onClick={ () => setIsModalOpen( true ) }
				isBusy={ isLoading && isCaptureRequested }
				disabled={ ( isLoading && isCaptureRequested ) || isRequesting }
			>
				{ children || __( 'Capture', 'woocommerce-payments' ) }
			</Button>

			{ isModalOpen && (
				<Modal
					title={ __(
						'Enter Capture Amount',
						'woocommerce-payments'
					) }
					onRequestClose={ () => setIsModalOpen( false ) }
				>
					<TextControl
						label={ __( 'Amount', 'woocommerce-payments' ) }
						value={ amount }
						type="number"
						onChange={ ( value ) => {
							setError( null );
							setAmount( parseFloat( value ) );
						} }
					/>
					<Button isPrimary onClick={ handleCapture }>
						{ __( 'Submit Capture', 'woocommerce-payments' ) }
					</Button>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
				</Modal>
			) }
		</>
	);
};

export default CaptureAuthorizationButton;
