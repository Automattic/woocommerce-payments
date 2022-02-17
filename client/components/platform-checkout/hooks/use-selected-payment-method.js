/**
 * External dependencies
 */
import { useEffect, useState } from 'react';

// hook for checking if WCPay is selected.
const useSelectedPaymentMethod = () => {
	const [ isWCPayChosen, setIsWCPayChosen ] = useState(
		document.querySelector( '#payment_method_woocommerce_payments' )
			?.checked
	);

	const [ isNewPaymentTokenChosen, setNewPaymentTokenChosen ] = useState(
		document.querySelector( '#wc-woocommerce_payments-payment-token-new' )
			?.checked
	);

	const getWCPayRadioButtonStatus = () => {
		setIsWCPayChosen(
			document.querySelector( '#payment_method_woocommerce_payments' )
				?.checked
		);
	};

	const getNewPaymentTokenRadioButtonStatus = () => {
		setNewPaymentTokenChosen(
			document.querySelector(
				'#wc-woocommerce_payments-payment-token-new'
			)?.checked
		);
	};

	useEffect( () => {
		const paymentMethods = document.querySelectorAll(
			'[type=radio][name="payment_method"]'
		);
		paymentMethods.forEach( ( paymentMethod ) => {
			paymentMethod.addEventListener(
				'change',
				getWCPayRadioButtonStatus
			);
		} );

		const paymentTokens = document.querySelectorAll(
			'[type=radio][name="wc-woocommerce_payments-payment-token"]'
		);
		paymentTokens.forEach( ( paymentToken ) => {
			paymentToken.addEventListener(
				'change',
				getNewPaymentTokenRadioButtonStatus
			);
		} );

		return () => {
			paymentMethods.forEach( ( paymentMethod ) => {
				paymentMethod.removeEventListener(
					'change',
					getWCPayRadioButtonStatus
				);
			} );

			paymentTokens.forEach( ( paymentToken ) => {
				paymentToken.removeEventListener(
					'change',
					getNewPaymentTokenRadioButtonStatus
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
