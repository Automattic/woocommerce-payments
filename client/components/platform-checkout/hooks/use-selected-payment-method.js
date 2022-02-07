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

	const getWCPayCheckboxStatus = () => {
		setIsWCPayChosen(
			document.querySelector( '#payment_method_woocommerce_payments' )
				?.checked
		);
	};

	useEffect( () => {
		const paymentMethods = document.querySelectorAll(
			'[type=radio][name="payment_method"]'
		);
		paymentMethods.forEach( ( paymentMethod ) => {
			paymentMethod.addEventListener( 'change', getWCPayCheckboxStatus );
		} );

		return () => {
			paymentMethods.forEach( ( paymentMethod ) => {
				paymentMethod.removeEventListener(
					'change',
					getWCPayCheckboxStatus
				);
			} );
		};
	}, [] );

	return {
		isWCPayChosen,
	};
};

export default useSelectedPaymentMethod;
