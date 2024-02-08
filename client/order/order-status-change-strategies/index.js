/**
 * External dependencies
 */
import ReactDOM from 'react-dom';
import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import RefundConfirmationModal from '../refund-confirm-modal';
import { getConfig } from 'utils/order';
import CancelConfirmationModal from '../cancel-confirm-modal';
import CancelAuthorizationConfirmationModal from '../cancel-authorization-confirm-modal';
import GenericConfirmationModal from '../generic-confirmation-modal';

// Map status changes to strategies
export const statusChangeStrategies = {
	'wc-refunded': handleRefundedStatus,
	'wc-cancelled': handleCancelledStatus,
	'wc-processing': handleProcessingStatus,
	'wc-failed': handleFailedStatus,
	'wc-completed': handleCompletedStatus,
	'wc-pending': handlePendingStatus,
	'wc-checkout-draft': handleCheckoutDraftStatus,
	// Add other specific status changes if needed
};

function renderModal( modalToRender ) {
	const container = document.createElement( 'div' );
	container.id = 'wcpay-orderstatus-confirm-container';
	document.body.appendChild( container );
	ReactDOM.render( modalToRender, container );
}

function cancelAuthorization( orderStatus ) {
	renderModal(
		<CancelAuthorizationConfirmationModal
			originalOrderStatus={ orderStatus }
		/>
	);
}

function captureAuthorization( originalOrderStatus ) {
	renderModal(
		<GenericConfirmationModal
			title={ __( 'Capture Authorization', 'woocommerce-payments' ) }
			confirmButtonText={ __( 'Capture', 'woocommerce-payments' ) }
			cancelButtonText={ __( 'Cancel', 'woocommerce-payments' ) }
			confirmationMessage={ __(
				'Are you sure you want to capture the authorization?',
				'woocommerce-payments'
			) }
			onConfirm={ () => {
				const orderEditForm =
					document
						.querySelector( '#order_status' )
						?.closest( 'form' ) || null;
				if ( orderEditForm !== null ) {
					orderEditForm.submit();
				}
			} }
			onCancel={ () => {
				const orderStatusElement = document.querySelector(
					'#order_status'
				);
				if ( orderStatusElement !== null ) {
					orderStatusElement.value = originalOrderStatus;
					orderStatusElement.dispatchEvent( new Event( 'change' ) );
				}
			} }
		/>
	);
}

function maybeCancelAuthorization( orderStatus ) {
	const hasOpenAuthorization = getConfig( 'hasOpenAuthorization' );
	if ( hasOpenAuthorization ) {
		cancelAuthorization( orderStatus );
	}
}

function maybeCaptureAuthorization( orderStatus ) {
	const hasOpenAuthorization = getConfig( 'hasOpenAuthorization' );
	if ( hasOpenAuthorization ) {
		captureAuthorization( orderStatus );
	}
}

export function renderRefundConfirmationModal(
	orderStatus,
	canRefund,
	refundAmount
) {
	if ( ! canRefund ) {
		dispatch( 'core/notices' ).createErrorNotice(
			__( 'Order cannot be refunded', 'woocommerce-payments' )
		);
		return;
	}
	if ( refundAmount <= 0 ) {
		dispatch( 'core/notices' ).createErrorNotice(
			__( 'Invalid Refund Amount', 'woocommerce-payments' )
		);
		return;
	}
	renderModal(
		<RefundConfirmationModal
			orderStatus={ orderStatus }
			refundAmount={ refundAmount }
			formattedRefundAmount={ getConfig( 'formattedRefundAmount' ) }
			refundedAmount={ getConfig( 'refundedAmount' ) }
		/>
	);
}

export function handleRefundedStatus( orderStatus ) {
	if ( orderStatus === 'wc-refunded' ) {
		return;
	}
	const canRefund = getConfig( 'canRefund' );
	const refundAmount = getConfig( 'refundAmount' );

	renderRefundConfirmationModal( orderStatus, canRefund, refundAmount );
}

export function handleCancelledStatus( orderStatus ) {
	if ( orderStatus === 'wc-cancelled' ) {
		return;
	}
	const hasOpenAuthorization = getConfig( 'hasOpenAuthorization' );
	const canRefund = getConfig( 'canRefund' );
	const refundAmount = getConfig( 'refundAmount' );

	// If order has an uncaptured authorization, confirm
	// that merchant indeed wants to cancel both the order
	// and the authorization.
	maybeCancelAuthorization( orderStatus );

	// If it is possible to refund an order, double check that
	// merchants indeed wants to cancel, or if they just want to
	// refund.
	if ( ! hasOpenAuthorization && canRefund && refundAmount > 0 ) {
		renderModal(
			<CancelConfirmationModal originalOrderStatus={ orderStatus } />
		);
	}
}

export function handleProcessingStatus( orderStatus ) {
	if ( orderStatus === 'wc-processing' ) {
		return;
	}
	maybeCaptureAuthorization( orderStatus );
}

export function handleFailedStatus( orderStatus ) {
	if ( orderStatus === 'wc-failed' ) {
		return;
	}

	maybeCancelAuthorization( orderStatus );
}

export function handleCompletedStatus( orderStatus ) {
	if ( orderStatus === 'wc-completed' ) {
		return;
	}
	maybeCaptureAuthorization( orderStatus );
}

export function handlePendingStatus( orderStatus ) {
	if ( orderStatus === 'wc-pending' ) {
		return;
	}

	maybeCancelAuthorization( orderStatus );
}

export function handleCheckoutDraftStatus( orderStatus ) {
	if ( orderStatus === 'wc-checkout-draft' ) {
		return;
	}

	maybeCancelAuthorization( orderStatus );
}

export function handleGenericStatusChange() {
	// Generic handler for any other status changes
	console.log( 'No specific action defined for this status change.' );
}
