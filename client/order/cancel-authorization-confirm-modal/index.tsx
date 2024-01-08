/** @format */
/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import { useState } from '@wordpress/element';
/**
 * Internal dependencies
 */
import ConfirmationModal from 'wcpay/components/confirmation-modal';

interface CancelAuthorizationConfirmationModalProps {
	originalOrderStatus: string;
}

const CancelAuthorizationConfirmationModal: React.FunctionComponent< CancelAuthorizationConfirmationModalProps > = ( {
	originalOrderStatus,
} ) => {
	const [
		isCancelAuthorizationConfirmationModalOpen,
		setIsCancelAuthorizationConfirmationModalOpen,
	] = useState( true );

	const closeModal = (): void => {
		setIsCancelAuthorizationConfirmationModalOpen( false );
	};

	const handleCancelOrder = (): void => {
		const orderEditForm: HTMLFormElement | null =
			document.querySelector( '#order_status' )?.closest( 'form' ) ||
			null;
		if ( null !== orderEditForm ) {
			orderEditForm.submit();
		}
	};

	const doNotCancel = (): void => {
		const orderStatusElement: HTMLInputElement | null = document.querySelector(
			'#order_status'
		);
		if ( null !== orderStatusElement ) {
			orderStatusElement.value = originalOrderStatus;
			orderStatusElement.dispatchEvent( new Event( 'change' ) );
		}
		closeModal();
	};

	const cancelOrder = (): void => {
		handleCancelOrder();
		closeModal();
	};

	const buttonContent = (
		<>
			<Button isSecondary onClick={ doNotCancel }>
				{ __( 'Do Nothing', 'woocommerce-payments' ) }
			</Button>
			<Button isPrimary onClick={ cancelOrder }>
				{ __(
					'Cancel order and authorization',
					'woocommerce-payments'
				) }
			</Button>
		</>
	);

	const confirmationMessage = interpolateComponents( {
		mixedString: __(
			'This order has been {{authorizedNotCaptured/}} yet. Changing the status to ' +
				'Cancelled will also {{cancelAuthorization/}}. Do you want to continue?',
			'woocommerce-payments'
		),
		components: {
			authorizedNotCaptured: (
				<a
					target="_blank"
					href="https://woo.com/document/woopayments/settings-guide/authorize-and-capture/#authorize-vs-capture"
					rel="noopener noreferrer"
				>
					{ __(
						'authorized but not captured',
						'woocommerce-payments'
					) }
				</a>
			),
			cancelAuthorization: (
				<a
					target="_blank"
					href="https://woo.com/document/woopayments/settings-guide/authorize-and-capture/#cancelling-authorizations"
					rel="noopener noreferrer"
				>
					{ __( 'cancel the authorization', 'woocommerce-payments' ) }
				</a>
			),
			doNothingBold: (
				<b>{ __( 'Do Nothing', 'woocommerce-payments' ) }</b>
			),
			cancelOrderBold: (
				<b>
					{ __(
						'Cancel order and authorization',
						'woocommerce-payments'
					) }
				</b>
			),
		},
	} );

	return (
		<>
			{ isCancelAuthorizationConfirmationModalOpen && (
				<ConfirmationModal
					title={ __(
						'Cancel authorization',
						'woocommerce-payments'
					) }
					isDismissible={ false }
					className="cancel-authorization-confirmation-modal"
					actions={ buttonContent }
					onRequestClose={ () => {
						return false;
					} }
				>
					<p>{ confirmationMessage }</p>
				</ConfirmationModal>
			) }
		</>
	);
};

export default CancelAuthorizationConfirmationModal;
