/**
 * External dependencies
 */
import React, { useCallback, useEffect, useRef, useState } from 'react';

const shouldConfirmPaymentMethodDeactivation = ( clickEvent ) => {
	// the click event can happen on any of the elements in the table of gateways
	// we are interested in the clicks on the anchor and its children
	const anchor = clickEvent.target.closest(
		'.wc-payment-gateway-method-toggle-enabled'
	);
	if ( ! anchor ) {
		return false;
	}

	// we are only interested on the click on the "woocommerce payments" payment method
	if ( ! anchor.closest( 'tr[data-gateway_id="woocommerce_payments"]' ) ) {
		return false;
	}

	// stuff is loading, user might have interacted with it, don't mess with it
	if ( anchor.querySelector( '.woocommerce-input-toggle--loading' ) ) {
		return false;
	}

	// only when the thing is active, we want to show the confirmation dialog.
	// please note that when the user has interacted with the toggles,
	// it can happen that both classes `woocommerce-input-toggle--disabled` and `woocommerce-input-toggle--enabled`
	// are applied to the element.
	return ! anchor.querySelector( '.woocommerce-input-toggle--disabled' );
};

const PaymentGatewaysConfirmation = () => {
	const cachedClickEvent = useRef( null );
	const hasUserConfirmedDeactivation = useRef( false );
	const [
		isConfirmationDialogVisible,
		setIsConfirmationDialogVisible,
	] = useState( false );

	const handleDialogConfirmation = useCallback( () => {
		const paymentGatewayToggle = document.querySelector(
			'tr[data-gateway_id="woocommerce_payments"] .wc-payment-gateway-method-toggle-enabled'
		);
		if ( ! paymentGatewayToggle ) {
			return;
		}

		setIsConfirmationDialogVisible( false );
		hasUserConfirmedDeactivation.current = true;
		paymentGatewayToggle.dispatchEvent( cachedClickEvent.current );
		cachedClickEvent.current = null;
		hasUserConfirmedDeactivation.current = false;
	}, [ setIsConfirmationDialogVisible ] );

	const handleDialogDismissal = useCallback( () => {
		setIsConfirmationDialogVisible( false );
		cachedClickEvent.current = null;
		hasUserConfirmedDeactivation.current = false;
	}, [ setIsConfirmationDialogVisible ] );

	useEffect( () => {
		const gatewaysTable = document.querySelector( '.wc_gateways' );
		if ( ! gatewaysTable ) {
			return;
		}

		const handler = ( event ) => {
			if ( true === hasUserConfirmedDeactivation.current ) {
				return;
			}

			if ( ! shouldConfirmPaymentMethodDeactivation( event ) ) {
				return;
			}

			// we arrived at this point.
			// it means that the user clicked on the deactivation of the WC Payments gateway
			// we need to show the confirmation dialog
			cachedClickEvent.current = new MouseEvent( 'click', event );
			event.preventDefault();
			event.stopImmediatePropagation();

			setIsConfirmationDialogVisible( true );
		};

		gatewaysTable.addEventListener( 'click', handler );

		return () => {
			gatewaysTable.removeEventListener( 'click', handler );
		};
	}, [ setIsConfirmationDialogVisible ] );

	if ( ! isConfirmationDialogVisible ) {
		return null;
	}

	return (
		<div>
			<h1>Are you sure you want to disable?</h1>
			<button onClick={ handleDialogDismissal }>
				No, do not disable
			</button>
			<button onClick={ handleDialogConfirmation }>Yes, disable</button>
		</div>
	);
};

export default PaymentGatewaysConfirmation;
