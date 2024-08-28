/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

document.addEventListener(
	'click',
	function ( event ) {
		const copyNumberButton = event.target?.closest(
			'.js-woopayments-copy-test-number'
		);
		if ( ! copyNumberButton ) {
			return;
		}

		event.preventDefault();
		const number = copyNumberButton.parentElement.querySelector(
			'.js-woopayments-test-number'
		).innerText;

		if ( ! navigator.clipboard ) {
			prompt(
				__( 'Copy the test number:', 'woocommerce-payments' ),
				number
			);
			return;
		}
		navigator.clipboard.writeText( number );

		window.wp?.data
			?.dispatch( 'core/notices' )
			?.createInfoNotice(
				__(
					'Test number copied to your clipboard!',
					'woocommerce-payments'
				),
				{
					// the unique `id` prevents the JS from creating multiple notices with the same text before they're dismissed.
					id: 'woopayments/test-number-copied',
					type: 'snackbar',
					context: 'wc/checkout/payments',
				}
			);
	},
	false
);
