/* global jQuery,woocommerce_admin_meta_boxes */
/** @format */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
/**
 * Internal dependencies
 */
import ConfirmationModal from 'wcpay/components/confirmation-modal';
import './styles.scss';

const RefundConfirmationModal = ( {
	orderStatus,
	refundAmount,
	formattedRefundAmount,
	refundedAmount,
} ) => {
	const [
		isRefundConfirmationModalOpen,
		setIsRefundConfirmationModalOpen,
	] = useState( true );

	const blockUI = () => {
		jQuery( '.refund-confirmation-modal' ).block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6,
			},
		} );
	};

	const unblockUI = () => {
		jQuery( '.refund-confirmation-modal' ).unblock();
	};

	const closeModal = () => {
		setIsRefundConfirmationModalOpen( false );
	};

	const resetOrderStatus = () => {
		jQuery( '#order_status' ).val( orderStatus ).change();
	};

	const handleRefundCancel = () => {
		resetOrderStatus();
		closeModal();
	};

	const handleRefundConfirm = () => {
		blockUI();
		jQuery.ajax( {
			type: 'post',
			url: woocommerce_admin_meta_boxes.ajax_url,
			data: {
				action: 'woocommerce_refund_line_items',
				order_id: woocommerce_admin_meta_boxes.post_id,
				security: woocommerce_admin_meta_boxes.order_item_nonce,
				refund_amount: refundAmount,
				refunded_amount: refundedAmount,
				api_refund: true,
			},
			beforeSend: function () {
				blockUI();
			},
			success: function ( response ) {
				if ( true === response.success ) {
					// Refresh the page to show the refunded status
					window.location.reload();
				} else {
					resetOrderStatus();
					window.alert( response.data.error );
				}
			},
			complete: function () {
				unblockUI();
				closeModal();
			},
		} );
	};

	return (
		<>
			{ isRefundConfirmationModalOpen && (
				<ConfirmationModal
					title={ __(
						'Refund order in full',
						'woocommerce-payments'
					) }
					isDismissible={ false }
					className="refund-confirmation-modal"
					actions={
						<>
							<Button isSecondary onClick={ handleRefundCancel }>
								{ __( 'Cancel', 'woocommerce-payments' ) }
							</Button>
							<Button isPrimary onClick={ handleRefundConfirm }>
								{ sprintf(
									__( 'Refund %s', 'woocommerce-payments' ),
									formattedRefundAmount
								) }
							</Button>
						</>
					}
				>
					<p>
						{ __(
							"Issue a full refund back to your customer's credit card using WooCommerce Payments." +
								'This action can not be undone. To issue a partial refund, click "Cancel", and use ' +
								'the "Refund" button in the order details below.',
							'woocommerce-payments'
						) }
					</p>
				</ConfirmationModal>
			) }
		</>
	);
};
export default RefundConfirmationModal;
