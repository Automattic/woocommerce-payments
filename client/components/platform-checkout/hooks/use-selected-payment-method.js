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

const getNewPaymentTokenRadioButtonStatus = () =>
	document.querySelector( '#wc-woocommerce_payments-payment-token-new' )
		?.checked ||
	! document.querySelector(
		'[type=radio][name="wc-woocommerce_payments-payment-token"]'
	);

// hook for checking if WCPay is selected.
const useSelectedPaymentMethod = ( isBlocksCheckout ) => {
	const [ isWCPayChosen, setIsWCPayChosen ] = useState(
		getWCPayRadioButtonStatus( isBlocksCheckout )
	);

	const [ isNewPaymentTokenChosen, setNewPaymentTokenChosen ] = useState(
		getNewPaymentTokenRadioButtonStatus()
	);

	const updateIsWCPayChosen = () => {
		setIsWCPayChosen( getWCPayRadioButtonStatus );
	};

	const updateIsNewPaymentTokenChosen = () => {
		setNewPaymentTokenChosen( getNewPaymentTokenRadioButtonStatus );
	};

	useEffect( () => {
		const paymentMethods = document.querySelectorAll(
			'[type=radio][name="payment_method"]'
		);
		paymentMethods.forEach( ( paymentMethod ) => {
			paymentMethod.addEventListener( 'change', updateIsWCPayChosen );
		} );

		const paymentTokens = document.querySelectorAll(
			'[type=radio][name="wc-woocommerce_payments-payment-token"]'
		);
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
	}, [] );

	return {
		isWCPayChosen,
		isNewPaymentTokenChosen,
	};
};

export default useSelectedPaymentMethod;
