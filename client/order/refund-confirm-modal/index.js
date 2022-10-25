/* global jQuery */
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
import { formatCurrency } from 'utils/currency';
import ConfirmationModal from 'wcpay/components/confirmation-modal';
import './styles.scss';

const RefundConfirmationModal = ( {
	orderStatus,
	refundAmount,
	refundedAmount,
	currencyCode,
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
									formatCurrency( refundAmount, currencyCode )
								) }
							</Button>
						</>
					}
				>
					<p>
						Issue a full refund back to your customer's credit card
						using WooCommerce Payments. This action can not be
						undone. To issue a partial refund, click "Cancel", and
						use the "Refund" button in the order details below.
					</p>
				</ConfirmationModal>
			) }
		</>
	);
};
export default RefundConfirmationModal;
