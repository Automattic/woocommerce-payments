/**
 * External dependencies
 */
import { useEffect, useState } from 'react';

const getWCPayRadioButtonStatus = ( isBlocksCheckout ) =>
	isBlocksCheckout
		? document.querySelector(
				'#radio-control-wc-payment-method-options-woocommerce_payments'
		  )?.checked
		: document.querySelector( '#payment_method_woocommerce_payments' )
				?.checked;

const getNewPaymentTokenRadioButtonStatus = ( isBlocksCheckout ) =>
	isBlocksCheckout
		? document.querySelector(
				'#radio-control-wc-payment-method-options-woocommerce_payments'
		  )?.checked
		: document.querySelector( '#wc-woocommerce_payments-payment-token-new' )
				?.checked ||
		  ! document.querySelector(
				'[type=radio][name="wc-woocommerce_payments-payment-token"]'
		  );

const getPaymentMethods = ( isBlocksCheckout ) => {
	if ( isBlocksCheckout ) {
		// For blocks checkout there is no common selector to find all the payment methods including the
		// saved tokens. Thus need to concate them here to make a whole list.
		return [
			...document.querySelectorAll(
				'[type=radio][name="radio-control-wc-payment-method-options"]'
			),
			...document.querySelectorAll(
				'[type=radio][name="radio-control-wc-payment-method-saved-tokens"]'
			),
		];
	}
	// for classic checkout
	return document.querySelectorAll( '[type=radio][name="payment_method"]' );
};

const getPaymentTokens = ( isBlocksCheckout ) => {
	return isBlocksCheckout
		? document.querySelectorAll(
				'[type=radio][name="radio-control-wc-payment-method-saved-tokens"]'
		  )
		: document.querySelectorAll(
				'[type=radio][name="wc-woocommerce_payments-payment-token"]'
		  );
};

// hook for checking if WCPay is selected.
const useSelectedPaymentMethod = ( isBlocksCheckout ) => {
	const [ isWCPayChosen, setIsWCPayChosen ] = useState(
		getWCPayRadioButtonStatus( isBlocksCheckout )
	);

	const [ isNewPaymentTokenChosen, setNewPaymentTokenChosen ] = useState(
		getNewPaymentTokenRadioButtonStatus( isBlocksCheckout )
	);

	useEffect( () => {
		// hides the `Save payment information to my account for future purchases` checkbox.
		const hideCheckbox = () => {
			const checkbox = document.querySelector(
				'.wc-block-components-payment-methods__save-card-info'
			);
			checkbox.style.display = 'none';
		};

		const updateIsWCPayChosen = () => {
			const WCPayRadioButtonStatus = getWCPayRadioButtonStatus(
				isBlocksCheckout
			);
			setIsWCPayChosen( WCPayRadioButtonStatus );

			if ( isBlocksCheckout && WCPayRadioButtonStatus ) {
				hideCheckbox();
			}
		};

		const updateIsNewPaymentTokenChosen = () => {
			setNewPaymentTokenChosen(
				getNewPaymentTokenRadioButtonStatus( isBlocksCheckout )
			);
		};

		const paymentMethods = getPaymentMethods( isBlocksCheckout );

		paymentMethods.forEach( ( paymentMethod ) => {
			paymentMethod.addEventListener( 'change', updateIsWCPayChosen );
		} );

		const paymentTokens = getPaymentTokens( isBlocksCheckout );
		paymentTokens.forEach( ( paymentToken ) => {
			paymentToken.addEventListener(
				'change',
				updateIsNewPaymentTokenChosen
			);
		} );

		return () => {
			paymentMethods.forEach( ( paymentMethod ) => {
				paymentMethod.removeEventListener(
					'change',
					updateIsWCPayChosen
				);
			} );

			paymentTokens.forEach( ( paymentToken ) => {
				paymentToken.removeEventListener(
					'change',
					updateIsNewPaymentTokenChosen
				);
			} );
		};
	}, [ isBlocksCheckout ] );

	return {
		isWCPayChosen,
		isNewPaymentTokenChosen,
	};
};

export default useSelectedPaymentMethod;
