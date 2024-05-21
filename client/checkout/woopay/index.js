/* global jQuery */
/**
 * External dependencies
 */
import ReactDOM from 'react-dom';

/**
 * External dependencies
 */
import CheckoutPageSaveUser from 'wcpay/components/woopay/save-user/checkout-page-save-user';
import intlTelInput from 'intl-tel-input';
import utils from 'iti/utils';

const renderSaveUserSection = () => {
	const saveUserSection = document.getElementsByClassName(
		'woopay-save-new-user-container'
	)?.[ 0 ];

	if ( saveUserSection ) {
		return;
	}

	const blocksCheckout = document.getElementsByClassName(
		'wc-block-checkout'
	);

	if ( blocksCheckout.length ) {
		const checkoutPageSaveUserContainer = document.createElement( 'div' );
		const paymentOptions = document.getElementsByClassName(
			'wp-block-woocommerce-checkout-payment-block'
		)?.[ 0 ];

		checkoutPageSaveUserContainer.className =
			'wc-block-checkout__payment-method wp-block-woocommerce-checkout-remember-block ';
		checkoutPageSaveUserContainer.id = 'remember-me';

		if ( paymentOptions ) {
			const inputWrapper = document.querySelector(
				'.wc-block-components-text-input.wc-block-components-address-form__woocommerce-payments--woopay-phone-number'
			);
			inputWrapper.style.display = 'none';

			const createAccountCheckbox = document.querySelector(
				'#additional-information-woocommerce-payments--create-woopay-account'
			);
			createAccountCheckbox.onchange = function ( e ) {
				if ( e.target.checked ) {
					inputWrapper.style.display = 'block';
				} else {
					inputWrapper.style.display = 'none';
				}
			};

			const input = document.querySelector(
				'#additional-information-woocommerce-payments--woopay-phone-number'
			);

			intlTelInput( input, {
				customPlaceholder: () => '',
				separateDialCode: true,
				hiddenInput: 'full',
				utilsScript: utils,
				dropdownContainer: document.body,
				// ...phoneCountries,
			} );
		}
	} else {
		const checkoutPageSaveUserContainer = document.createElement( 'div' );
		checkoutPageSaveUserContainer.className =
			'woopay-save-new-user-container';

		const placeOrderButton = document.getElementsByClassName(
			'form-row place-order'
		)?.[ 0 ];
		const buttonParent = placeOrderButton?.parentNode;

		if ( placeOrderButton && buttonParent ) {
			buttonParent.insertBefore(
				checkoutPageSaveUserContainer,
				placeOrderButton
			);

			ReactDOM.render(
				<CheckoutPageSaveUser isBlocksCheckout={ false } />,
				checkoutPageSaveUserContainer
			);
		}
	}
};

window.addEventListener( 'load', () => {
	renderSaveUserSection();
} );

// mount component again if parent fragment if re-rendered after ajax request by woocommerce core
// https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/legacy/js/frontend/checkout.js#L372
jQuery( function ( $ ) {
	$( document ).ajaxComplete( function () {
		renderSaveUserSection();
	} );
} );
