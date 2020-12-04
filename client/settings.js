/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import AccountStatus from 'account-status';
import AccountFees from 'account-fees';

const statusContainer = document.getElementById( 'wcpay-account-status-container' );
if ( statusContainer ) {
	ReactDOM.render(
		<AccountStatus { ...wcpayAdminSettings } />,
		statusContainer
	);
}

const feesContainer = document.getElementById( 'wcpay-account-fees-container' );
if ( feesContainer ) {
  ReactDOM.render(
	  <AccountFees { ...wcpayAdminSettings } />,
	  feesContainer
  );
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
