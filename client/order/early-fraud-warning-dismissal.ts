/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { setEarlyFraudWarningDismissed } from 'wcpay/data/early-fraud-warnings/api';

const dismissedClass = 'wcpay-fraud-risk-efw--dismissed';
const errorClass = 'wcpay-fraud-risk-efw__error';

export const toggleEarlyFraudWarningDismissal = async (
	button: HTMLButtonElement
): Promise< void > => {
	const block = button.closest( '.wcpay-fraud-risk-efw' );
	if ( ! block || button.disabled ) {
		return;
	}

	block.querySelector( `.${ errorClass }` )?.remove();
	button.disabled = true;

	try {
		const { dismissed } = await setEarlyFraudWarningDismissed(
			Number( button.dataset.orderId ),
			button.dataset.dismissed === '1'
		);
		block.classList.toggle( dismissedClass, dismissed );

		// The pressed button is now hidden, so hand focus to the one that replaced it.
		block
			.querySelector< HTMLButtonElement >(
				dismissed
					? '.wcpay-efw-dismiss-toggle--undo'
					: '.wcpay-efw-dismiss-toggle--dismiss'
			)
			?.focus();
	} catch {
		const error = document.createElement( 'p' );
		error.className = errorClass;
		error.setAttribute( 'role', 'alert' );
		error.textContent = __(
			'There was an error updating the early fraud warning. Please try again later.',
			'woocommerce-payments'
		);
		button.closest( 'p' )?.after( error );
	} finally {
		button.disabled = false;
	}
};
