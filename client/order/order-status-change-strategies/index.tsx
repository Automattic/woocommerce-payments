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
import React from 'react';

interface StatusChangeStrategies {
	[ key: string ]: ( orderStatus: string ) => void;
}

function renderModal( modalToRender: JSX.Element ) {
	const container = document.createElement( 'div' );
	container.id = 'wcpay-orderstatus-confirm-container';
	document.body.appendChild( container );
	ReactDOM.render( modalToRender, container );
}

function triggerCancelAuthorizationModal( orderStatus: string ) {
	renderModal(
		<CancelAuthorizationConfirmationModal
			originalOrderStatus={ orderStatus }
		/>
	);
}

function triggerCaptureAuthorizationModal( originalOrderStatus: string ) {
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
				const orderEditForm: HTMLFormElement | null =
					document
						.querySelector( '#order_status' )
						?.closest( 'form' ) || null;
				if ( orderEditForm !== null ) {
					orderEditForm.submit();
				}
			} }
			onCancel={ () => {
				const orderStatusElement: HTMLInputElement | null = document.querySelector(
					'#order_status'
				);
				if ( orderStatusElement !== null ) {
					orderStatusElement.value = originalOrderStatus;
					orderStatusElement.dispatchEvent( new Event( 'change' ) );
				}
			} }
			confirmButtonLink={ '' }
			cancelButtonLink={ '' }
		/>
	);
}

export function renderRefundConfirmationModal(
	orderStatus: string,
	canRefund: boolean,
	refundAmount: number
): void {
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

export function handleRefundedStatus( orderStatus: string ): void {
	if ( orderStatus === 'wc-refunded' ) {
		return;
	}
	const canRefund = getConfig( 'canRefund' );
	const refundAmount = getConfig( 'refundAmount' );

	renderRefundConfirmationModal( orderStatus, canRefund, refundAmount );
}

export function handleCancelledStatus( orderStatus: string ): void {
	if ( orderStatus === 'wc-cancelled' ) {
		return;
	}
	const hasOpenAuthorization = getConfig( 'hasOpenAuthorization' );
	const canRefund = getConfig( 'canRefund' );
	const refundAmount = getConfig( 'refundAmount' );

	// Confirm that merchant indeed wants to cancel both the order
	// and the authorization.
	if ( hasOpenAuthorization ) {
		triggerCancelAuthorizationModal( orderStatus );
	}

	// If it is possible to refund an order, double check that
	// merchants indeed wants to cancel, or if they just want to
	// refund.
	if ( ! hasOpenAuthorization && canRefund && refundAmount > 0 ) {
		renderModal(
			<CancelConfirmationModal originalOrderStatus={ orderStatus } />
		);
	}
}

export function handleProcessingStatus( orderStatus: string ): void {
	if ( orderStatus === 'wc-processing' ) {
		return;
	}

	if ( getConfig( 'hasOpenAuthorization' ) ) {
		triggerCaptureAuthorizationModal( orderStatus );
	}
}

export function handleFailedStatus( orderStatus: string ): void {
	if ( orderStatus === 'wc-failed' ) {
		return;
	}

	if ( getConfig( 'hasOpenAuthorization' ) ) {
		triggerCancelAuthorizationModal( orderStatus );
	}
}

export function handleCompletedStatus( orderStatus: string ): void {
	if ( orderStatus === 'wc-completed' ) {
		return;
	}

	if ( getConfig( 'hasOpenAuthorization' ) ) {
		triggerCaptureAuthorizationModal( orderStatus );
	}
}

export function handlePendingStatus( orderStatus: string ): void {
	if ( orderStatus === 'wc-pending' ) {
		return;
	}

	if ( getConfig( 'hasOpenAuthorization' ) ) {
		triggerCancelAuthorizationModal( orderStatus );
	}
}

export function handleCheckoutDraftStatus( orderStatus: string ): void {
	if ( orderStatus === 'wc-checkout-draft' ) {
		return;
	}

	if ( getConfig( 'hasOpenAuthorization' ) ) {
		triggerCancelAuthorizationModal( orderStatus );
	}
}

export function handleGenericStatusChange(): void {
	// Generic handler for any other status changes
	// eslint-disable-next-line no-console
	console.log( 'No specific action defined for this status change.' );
}

// Map status changes to strategies
export const statusChangeStrategies: StatusChangeStrategies = {
	'wc-refunded': handleRefundedStatus,
	'wc-cancelled': handleCancelledStatus,
	'wc-processing': handleProcessingStatus,
	'wc-failed': handleFailedStatus,
	'wc-completed': handleCompletedStatus,
	'wc-pending': handlePendingStatus,
	'wc-checkout-draft': handleCheckoutDraftStatus,
	// Add other specific status changes if needed
};
