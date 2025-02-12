/**
 * External dependencies
 */
import { useEffect, useState } from 'react';
import { useSelect } from '@wordpress/data';
import { PAYMENT_STORE_KEY } from '@woocommerce/block-data'; // eslint-disable-line import/no-unresolved

const getWCPayRadioButtonStatus = () => {
	return document.querySelector( '#payment_method_woocommerce_payments' )
		?.checked;
};

const getNewPaymentTokenRadioButtonStatus = () =>
	document.querySelector( '#wc-woocommerce_payments-payment-token-new' )
		?.checked ||
	! document.querySelector(
		'[type=radio][name="wc-woocommerce_payments-payment-token"]'
	);

const getPaymentMethods = () => {
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
	// For blocks checkout, we use the store to get the active payment method.
	const { isWCPayChosenOnBlocksCheckout } = useSelect( ( select ) => {
		const store = select( PAYMENT_STORE_KEY );
		return {
			isWCPayChosenOnBlocksCheckout:
				store.getActivePaymentMethod() === 'woocommerce_payments',
		};
	} );

	const [ isWCPayChosen, setIsWCPayChosen ] = useState(
		! isBlocksCheckout && getWCPayRadioButtonStatus()
	);

	const [ isNewPaymentTokenChosen, setNewPaymentTokenChosen ] = useState(
		! isBlocksCheckout && getNewPaymentTokenRadioButtonStatus()
	);

	useEffect( () => {
		if ( isBlocksCheckout ) {
			return;
		}

		// hides the `Save payment information to my account for future purchases` checkbox.
		const hideCheckbox = () => {
			const checkbox = document.querySelector(
				'.wc-block-components-payment-methods__save-card-info'
			);
			if ( checkbox ) {
				checkbox.style.display = 'none';
			}
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

		const paymentMethods = getPaymentMethods();

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
		isWCPayChosen: isBlocksCheckout
			? isWCPayChosenOnBlocksCheckout
			: isWCPayChosen,
		isNewPaymentTokenChosen: isBlocksCheckout
			? isWCPayChosenOnBlocksCheckout
			: isNewPaymentTokenChosen,
	};
};

export default useSelectedPaymentMethod;
