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
import OrderStatusConfirmationModal from '../order-status-confirmation-modal';
import React from 'react';

interface StatusChangeStrategies {
	[ key: string ]: ( orderStatus: string, newOrderStatus: string ) => void;
}

const OrderStatus = {
	CANCELLED: __( 'cancelled', 'woocommerce-payments' ),
	COMPLETED: __( 'completed', 'woocommerce-payments' ),
	FAILED: __( 'failed', 'woocommerce-payments' ),
	ON_HOLD: __( 'on-hold', 'woocommerce-payments' ),
	PENDING: __( 'pending', 'woocommerce-payments' ),
	PROCESSING: __( 'processing', 'woocommerce-payments' ),
	REFUNDED: __( 'refunded', 'woocommerce-payments' ),
	TRASH: __( 'trash', 'woocommerce-payments' ),
};

const OrderStatusCancelled = 'wc-cancelled';
const OrderStatusCompleted = 'wc-completed';
const OrderStatusFailed = 'wc-failed';
const OrderStatusOnHold = 'wc-on-hold';
const OrderStatusPending = 'wc-pending';
const OrderStatusProcessing = 'wc-processing';
const OrderStatusRefunded = 'wc-refunded';
const OrderStatusTrash = 'wc-trash';

const OrderStatusLookup: { [ key: string ]: string } = {
	[ OrderStatusCancelled ]: OrderStatus.CANCELLED,
	[ OrderStatusCompleted ]: OrderStatus.COMPLETED,
	[ OrderStatusFailed ]: OrderStatus.FAILED,
	[ OrderStatusOnHold ]: OrderStatus.ON_HOLD,
	[ OrderStatusPending ]: OrderStatus.PENDING,
	[ OrderStatusProcessing ]: OrderStatus.PROCESSING,
	[ OrderStatusRefunded ]: OrderStatus.REFUNDED,
	[ OrderStatusTrash ]: OrderStatus.TRASH,
};

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
						'https://woocommerce.com/document/woopayments/settings-guide/authorize-and-capture/#authorize-vs-capture'
					}
					rel="noopener noreferrer"
				>
					{ __(
						'authorized but payment has not been captured',
						'woocommerce-payments'
					) }
				</a>
			),
			cancelAuthorization: (
				<a
					target="_blank"
					href="https://woocommerce.com/document/woopayments/settings-guide/authorize-and-capture/#cancelling-authorizations"
					rel="noopener noreferrer"
				>
					{ __( 'cancel the payment', 'woocommerce-payments' ) }
				</a>
			),
			newOrderStatus: <b>{ OrderStatusLookup[ newOrderStatus ] }</b>,
		},
	} );

	renderModal(
		<OrderStatusConfirmationModal
			title={ __( 'Cancel payment', 'woocommerce-payments' ) }
			confirmButtonText={ __(
				'Cancel order and payment',
				'woocommerce-payments'
			) }
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
						'https://woocommerce.com/document/woopayments/settings-guide/authorize-and-capture/#authorize-vs-capture'
					}
					rel="noopener noreferrer"
				>
					{ __(
						'authorized but payment has not been captured',
						'woocommerce-payments'
					) }
				</a>
			),
			captureAuthorization: (
				<a
					target="_blank"
					href={
						'https://woocommerce.com/document/woopayments/settings-guide/authorize-and-capture/#capturing-authorized-payments'
					}
					rel="noopener noreferrer"
				>
					{ __( 'capture the payment', 'woocommerce-payments' ) }
				</a>
			),
			newOrderStatus: <b>{ OrderStatusLookup[ newOrderStatus ] }</b>,
		},
	} );

	renderModal(
		<OrderStatusConfirmationModal
			title={ __( 'Capture payment', 'woocommerce-payments' ) }
			confirmButtonText={ __(
				'Complete order and capture payment',
				'woocommerce-payments'
			) }
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
	if ( orderStatus === OrderStatusRefunded ) {
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
	if ( orderStatus === OrderStatusCancelled ) {
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

		renderModal(
			<OrderStatusConfirmationModal
				title={ __( 'Cancel order', 'woocommerce-payments' ) }
				confirmButtonText={ __(
					'Cancel order',
					'woocommerce-payments'
				) }
				cancelButtonText={ __( 'Do Nothing', 'woocommerce-payments' ) }
				confirmationMessage={ confirmationMessage }
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
						orderStatusElement.dispatchEvent(
							new Event( 'change' )
						);
					}
				} }
			/>
		);
	}
}

function handleGenericStatusChange(): void {
	// Generic handler for any other status changes
	// eslint-disable-next-line no-console
	console.log( 'No specific action defined for this status change.' );
}

function maybeTriggerCaptureAuthorizationModal(
	orderStatus: string,
	newOrderStatus: string
): void {
	if ( orderStatus === OrderStatusCompleted ) {
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
	[ OrderStatusCancelled ]: handleCancelledStatus,
	[ OrderStatusCompleted ]: maybeTriggerCaptureAuthorizationModal,
	[ OrderStatusRefunded ]: handleRefundedStatus,
};

export default function getStatusChangeStrategy(
	orderStatus: string
): ( orderStatus: string, newOrderStatus: string ) => void {
	return statusChangeStrategies[ orderStatus ] || handleGenericStatusChange;
}
