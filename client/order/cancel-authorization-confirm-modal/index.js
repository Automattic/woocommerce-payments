/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import { useState } from '@wordpress/element';
/**
 * Internal dependencies
 */
import ConfirmationModal from 'wcpay/components/confirmation-modal';

const CancelAuthorizationConfirmationModal = ( { originalOrderStatus } ) => {
	const [
		isCancelAuthorizationConfirmationModalOpen,
		setIsCancelAuthorizationConfirmationModalOpen,
	] = useState( true );

	const closeModal = () => {
		setIsCancelAuthorizationConfirmationModalOpen( false );
	};

	const doNotCancel = () => {
		const orderStatusElement = document.querySelector( '#order_status' );
		orderStatusElement.value = originalOrderStatus;
		orderStatusElement.dispatchEvent( new Event( 'change' ) );
		closeModal();
	};

	const cancelOrder = () => {
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
				>
					<p>{ confirmationMessage }</p>
				</ConfirmationModal>
			) }
		</>
	);
};
export default CancelAuthorizationConfirmationModal;
