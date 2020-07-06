/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';
import { __ } from '@wordpress/i18n';
import { getQuery } from '@woocommerce/navigation';
/**
 * Internal dependencies
 */
import AccountStatus from 'account-status';

ReactDOM.render(
	<AccountStatus { ...wcpayAdminSettings } />,
	document.getElementById( 'wcpay-account-status-container' )
);

if ( isWCPaySettingsPage() ) {
	const settingsForm = document.querySelector( 'form#mainform' );
	const manualCaptureCheckbox = document.getElementById( 'woocommerce_woocommerce_payments_manual_capture' );

	if ( settingsForm && manualCaptureCheckbox && ! manualCaptureCheckbox.checked ) {
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
}

function isWCPaySettingsPage() {
	const { page, tab, section } = getQuery();
	return 'wc-settings' === page && 'checkout' === tab && 'woocommerce_payments' === section;
}
