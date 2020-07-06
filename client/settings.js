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
	const saveButton = document.querySelector( 'button[name="save"]' );
	const manualCaptureCheckbox = document.getElementById( 'woocommerce_woocommerce_payments_manual_capture' );

	if ( saveButton && manualCaptureCheckbox && ! manualCaptureCheckbox.checked ) {
		saveButton.addEventListener( 'click', ( e ) => {
			if ( manualCaptureCheckbox.checked ) {
				const hasUserConfirmed = confirm(
					__(
						'When manual capture is enabled, you need to capture funds manually within 7 days of the order being placed, \
otherwise the authorization will be canceled alongside the order. Are you sure you want to enable it?',
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
