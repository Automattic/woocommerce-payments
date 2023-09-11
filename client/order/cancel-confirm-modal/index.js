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

const CancelConfirmationModal = ( { originalOrderStatus } ) => {
	const [
		isCancelConfirmationModalOpen,
		setIsCancelConfirmationModalOpen,
	] = useState( true );

	const closeModal = () => {
		setIsCancelConfirmationModalOpen( false );
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
				{ __( 'Cancel order', 'woocommerce-payments' ) }
			</Button>
		</>
	);

	const confirmationMessage = interpolateComponents( {
		mixedString: __(
			'Are you trying to issue a refund for this order? If so, please click ' +
				'{{doNothingBold/}} and see our documentation on {{howtoIssueRefunds/}}. If you want ' +
				'to mark this order as Cancelled without issuing a refund, click {{cancelOrderBold/}}.',
			'woocommerce-payments'
		),
		components: {
			doNothingBold: (
				<b>{ __( 'Do Nothing', 'woocommerce-payments' ) }</b>
			),
			cancelOrderBold: (
				<b>{ __( 'Cancel order', 'woocommerce-payments' ) }</b>
			),
			howtoIssueRefunds: (
				<a
					target="_blank"
					href="https://woocommerce.com/document/woopayments/managing-money/#refunds"
					rel="noopener noreferrer"
				>
					{ __( 'how to issue refunds', 'woocommerce-payments' ) }
				</a>
			),
		},
	} );

	return (
		<>
			{ isCancelConfirmationModalOpen && (
				<ConfirmationModal
					title={ __( 'Cancel order', 'woocommerce-payments' ) }
					isDismissible={ false }
					className="cancel-confirmation-modal"
					actions={ buttonContent }
				>
					<p>{ confirmationMessage }</p>
				</ConfirmationModal>
			) }
		</>
	);
};
export default CancelConfirmationModal;
