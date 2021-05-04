/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';
import AccountStatus from 'account-status';
import AccountFees from 'account-fees';
import enqueueFraudScripts from 'fraud-scripts';
import SettingsManager from 'settings/settings-manager';
import PaymentMethodSettings from './payment-method-settings';

const statusContainer = document.getElementById(
	'wcpay-account-status-container'
);
if ( statusContainer ) {
	ReactDOM.render(
		<AccountStatus { ...wcpayAdminSettings } />,
		statusContainer
	);
}

const feesContainer = document.getElementById( 'wcpay-account-fees-container' );
if ( feesContainer ) {
	ReactDOM.render( <AccountFees { ...wcpayAdminSettings } />, feesContainer );
}

const settingsForm = document.querySelector( 'form#mainform' );
const manualCaptureCheckbox = document.getElementById(
	'woocommerce_woocommerce_payments_manual_capture'
);

if (
	settingsForm &&
	manualCaptureCheckbox &&
	! manualCaptureCheckbox.checked
) {
	settingsForm.addEventListener( 'submit', ( e ) => {
		if ( manualCaptureCheckbox.checked ) {
			const hasUserConfirmed = confirm(
				__(
					'When manual capture is enabled, charges must be captured within 7 days of authorization, otherwise the \
authorization and order will be canceled. Are you sure you want to enable it?',
					'woocommerce-payments'
				)
			);
			if ( ! hasUserConfirmed ) {
				e.preventDefault();
			}
		}
	} );
}

window.addEventListener( 'load', () => {
	enqueueFraudScripts( wcpayAdminSettings.fraudServices );
} );

const settingsContainer = document.getElementById(
	'wcpay-account-settings-container'
);
if ( settingsContainer ) {
	ReactDOM.render( <SettingsManager />, settingsContainer );
}

const paymentMethodSettingsContainer = document.getElementById(
	'wcpay-payment-method-settings-container'
);
if ( paymentMethodSettingsContainer ) {
	const methodId = paymentMethodSettingsContainer.dataset.methodId;

	ReactDOM.render(
		<PaymentMethodSettings methodId={ methodId } />,
		paymentMethodSettingsContainer
	);
}

const paymentRequest = document.getElementById(
	'woocommerce_woocommerce_payments_payment_request'
);
const paymentRequestButtonType = document.getElementById(
	'woocommerce_woocommerce_payments_payment_request_button_type'
);

if ( paymentRequest && paymentRequestButtonType ) {
	// Payment Request button settings migrated and adapted from Stripe gateway extension.
	const findParent = ( el, selector ) => {
		while (
			( el = el.parentElement ) &&
			! ( el.matches || el.matchesSelector ).call( el, selector )
		);

		return el;
	};

	const toggleDisplay = ( el, display ) => {
		if ( el instanceof Element || el instanceof HTMLElement ) {
			if ( display ) {
				el.style.display = '';
			} else {
				el.style.display = 'none';
			}
		}
	};

	// Payment Request button event listeners.
	paymentRequest.addEventListener( 'change', () => {
		const inputIds = [
			'woocommerce_woocommerce_payments_payment_request_button_theme',
			'woocommerce_woocommerce_payments_payment_request_button_type',
			'woocommerce_woocommerce_payments_payment_request_button_height',
			'woocommerce_woocommerce_payments_payment_request_button_locations',
		];

		if ( paymentRequest.checked ) {
			inputIds.forEach( ( id ) => {
				toggleDisplay(
					findParent( document.getElementById( id ), 'tr' ),
					true
				);
			} );
		} else {
			inputIds.forEach( ( id ) => {
				toggleDisplay(
					findParent( document.getElementById( id ), 'tr' ),
					false
				);
			} );
		}

		paymentRequestButtonType.dispatchEvent( new Event( 'change' ) );
	} );

	// Toggle Custom Payment Request configs.
	paymentRequestButtonType.addEventListener( 'change', () => {
		if (
			'custom' === paymentRequestButtonType.value &&
			paymentRequest.checked
		) {
			toggleDisplay(
				findParent(
					document.getElementById(
						'woocommerce_woocommerce_payments_payment_request_button_label'
					),
					'tr'
				),
				true
			);
		} else {
			toggleDisplay(
				findParent(
					document.getElementById(
						'woocommerce_woocommerce_payments_payment_request_button_label'
					),
					'tr'
				),
				false
			);
		}

		if (
			'branded' === paymentRequestButtonType.value &&
			paymentRequest.checked
		) {
			toggleDisplay(
				findParent(
					document.getElementById(
						'woocommerce_woocommerce_payments_payment_request_button_branded_type'
					),
					'tr'
				),
				true
			);
		} else {
			toggleDisplay(
				findParent(
					document.getElementById(
						'woocommerce_woocommerce_payments_payment_request_button_branded_type'
					),
					'tr'
				),
				false
			);
		}
	} );

	paymentRequest.dispatchEvent( new Event( 'change' ) );
	paymentRequestButtonType.dispatchEvent( new Event( 'change' ) );
}
