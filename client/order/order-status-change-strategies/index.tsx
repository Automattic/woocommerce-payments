/**
 * External dependencies
 */
import ReactDOM from 'react-dom';
import { dispatch } from '@wordpress/data';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import RefundConfirmationModal from '../refund-confirm-modal';
import { getConfig } from 'utils/order';
import CancelConfirmationModal from '../cancel-confirm-modal';
import GenericConfirmationModal from '../generic-confirmation-modal';
import React from 'react';

interface StatusChangeStrategies {
	[ key: string ]: ( orderStatus: string, newOrderStatus: string ) => void;
}

function renderModal( modalToRender: JSX.Element ) {
	const container = document.createElement( 'div' );
	container.id = 'wcpay-orderstatus-confirm-container';
	document.body.appendChild( container );
	ReactDOM.render( modalToRender, container );
}

function triggerCancelAuthorizationModal(
	orderStatus: string,
	newOrderStatus: string
): void {
	const interpolatedMessage = interpolateComponents( {
		mixedString: __(
			'This order has been {{authorizedNotCaptured/}} yet. Changing the status to ' +
				'{{newOrderStatus/}} will also {{cancelAuthorization/}}. Do you want to continue?',
			'woocommerce-payments'
		),
		components: {
			authorizedNotCaptured: (
				<a
					target="_blank"
					href={
						'https://woo.com/document/woopayments/settings-guide/authorize-and-capture/#authorize-vs-capture'
					}
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
			newOrderStatus: <b>{ newOrderStatus }</b>,
		},
	} );

	renderModal(
		<GenericConfirmationModal
			title={ __( 'Cancel authorization', 'woocommerce-payments' ) }
			confirmButtonText={ __(
				'Cancel order and authorization',
				'woocommerce-payments'
			) }
			cancelButtonText={ __( 'Do Nothing', 'woocommerce-payments' ) }
			confirmationMessage={ interpolatedMessage }
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
					orderStatusElement.value = orderStatus;
					orderStatusElement.dispatchEvent( new Event( 'change' ) );
				}
			} }
		/>
	);
}

function triggerCaptureAuthorizationModal(
	orderStatus: string,
	newOrderStatus: string
): void {
	const interpolatedMessage = interpolateComponents( {
		mixedString: __(
			'This order has been {{authorizedNotCaptured/}} yet. Changing the status to ' +
				'{{newOrderStatus/}} will also {{captureAuthorization/}}. Do you want to continue?',
			'woocommerce-payments'
		),
		components: {
			authorizedNotCaptured: (
				<a
					target="_blank"
					href={
						'https://woo.com/document/woopayments/settings-guide/authorize-and-capture/#authorize-vs-capture'
					}
					rel="noopener noreferrer"
				>
					{ __(
						'authorized but not captured',
						'woocommerce-payments'
					) }
				</a>
			),
			captureAuthorization: (
				<a
					target="_blank"
					href={
						'https://woo.com/document/woopayments/settings-guide/authorize-and-capture/#capturing-authorized-payments'
					}
					rel="noopener noreferrer"
				>
					{ __( 'capture the payment', 'woocommerce-payments' ) }
				</a>
			),
			newOrderStatus: <b>{ newOrderStatus }</b>,
		},
	} );

	renderModal(
		<GenericConfirmationModal
			title={ __( 'Capture Authorization', 'woocommerce-payments' ) }
			confirmButtonText={ __( 'Capture', 'woocommerce-payments' ) }
			cancelButtonText={ __( 'Cancel', 'woocommerce-payments' ) }
			confirmationMessage={ interpolatedMessage }
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
					orderStatusElement.value = orderStatus;
					orderStatusElement.dispatchEvent( new Event( 'change' ) );
				}
			} }
		/>
	);
}

function renderRefundConfirmationModal(
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

function handleRefundedStatus( orderStatus: string ): void {
	if ( orderStatus === 'wc-refunded' ) {
		return;
	}
	const canRefund = getConfig( 'canRefund' );
	const refundAmount = getConfig( 'refundAmount' );

	renderRefundConfirmationModal( orderStatus, canRefund, refundAmount );
}

function handleCancelledStatus(
	orderStatus: string,
	newOrderStatus: string
): void {
	if ( orderStatus === 'wc-cancelled' ) {
		return;
	}
	const hasOpenAuthorization = getConfig( 'hasOpenAuthorization' );
	const canRefund = getConfig( 'canRefund' );
	const refundAmount = getConfig( 'refundAmount' );

	// Confirm that merchant indeed wants to cancel both the order
	// and the authorization.
	if ( hasOpenAuthorization ) {
		triggerCancelAuthorizationModal( orderStatus, newOrderStatus );
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

function handleGenericStatusChange(): void {
	// Generic handler for any other status changes
	// eslint-disable-next-line no-console
	console.log( 'No specific action defined for this status change.' );
}

function maybeTriggerCancelAuthorizationModal(
	orderStatus: string,
	newOrderStatus: string
): void {
	if (
		orderStatus === 'wc-checkout-draft' ||
		orderStatus === 'wc-failed' ||
		orderStatus === 'wc-pending'
	) {
		return;
	}

	if ( getConfig( 'hasOpenAuthorization' ) ) {
		triggerCancelAuthorizationModal( orderStatus, newOrderStatus );
	}
}

function maybeTriggerCaptureAuthorizationModal(
	orderStatus: string,
	newOrderStatus: string
): void {
	if ( orderStatus === 'wc-processing' || orderStatus === 'wc-completed' ) {
		return;
	}

	if ( getConfig( 'hasOpenAuthorization' ) ) {
		triggerCaptureAuthorizationModal( orderStatus, newOrderStatus );
	}
}

// Map status changes to strategies.
// If some status needs more complex logic, feel free to create a new function
// and add it to the map.
const statusChangeStrategies: StatusChangeStrategies = {
	'wc-cancelled': handleCancelledStatus,
	'wc-completed': maybeTriggerCaptureAuthorizationModal,
	'wc-failed': maybeTriggerCancelAuthorizationModal,
	'wc-pending': maybeTriggerCancelAuthorizationModal,
	'wc-processing': maybeTriggerCaptureAuthorizationModal,
	'wc-refunded': handleRefundedStatus,
};

export default function getStatusChangeStrategy(
	orderStatus: string
): ( orderStatus: string, newOrderStatus: string ) => void {
	return statusChangeStrategies[ orderStatus ] || handleGenericStatusChange;
}
