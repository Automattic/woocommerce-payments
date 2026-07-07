/**
 * External dependencies
 */
import { ExpressCheckoutElement } from '@stripe/react-stripe-js';
import { select } from '@wordpress/data';
import { useCallback, useMemo } from 'react';
/**
 * Internal dependencies
 */
import {
	shippingAddressChangeHandler,
	shippingRateChangeHandler,
} from '../../event-handlers';
import { useExpressCheckout } from '../hooks/use-express-checkout';
import {
	PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT,
	WC_STORE_CART,
} from 'wcpay/checkout/constants';
import {
	getPaymentMethodsOverride,
	adjustButtonHeights,
} from '../../utils/payment-method-overrides';

/**
 * ExpressCheckout express payment method component.
 *
 * @param {Object} props PaymentMethodProps.
 *
 * @return {ReactNode} Stripe Elements component.
 */
const ExpressCheckoutComponent = ( {
	api,
	billing,
	shippingData,
	setExpressPaymentError,
	onClick,
	onClose,
	expressPaymentMethod = '',
	buttonAttributes,
	paymentMethodTypes = [],
} ) => {
	const {
		buttonOptions,
		onButtonClick,
		onConfirm,
		onReady,
		onCancel,
		elements,
	} = useExpressCheckout( {
		api,
		billing,
		shippingData,
		onClick,
		onClose,
		setExpressPaymentError,
		paymentMethodTypes,
	} );
	const onShippingAddressChange = useCallback(
		( event ) =>
			shippingAddressChangeHandler(
				event,
				elements,
				setExpressPaymentError
			),
		[ elements, setExpressPaymentError ]
	);

	const onShippingRateChange = useCallback(
		( event ) =>
			shippingRateChangeHandler(
				event,
				elements,
				select( WC_STORE_CART )?.getCartData(),
				setExpressPaymentError
			),
		[ elements, setExpressPaymentError ]
	);

	const onElementsReady = useCallback(
		( event ) => {
			const paymentMethodContainer = document.getElementById(
				`express-payment-method-${ PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT }_${ expressPaymentMethod }`
			);

			const availablePaymentMethods = event.availablePaymentMethods || {};

			if (
				paymentMethodContainer &&
				! availablePaymentMethods[ expressPaymentMethod ]
			) {
				paymentMethodContainer.remove();
			}

			// Any actions that WooPayments needs to perform.
			onReady( event );
		},
		[ expressPaymentMethod, onReady ]
	);

	// The Cart & Checkout blocks provide unified styles across all buttons,
	// which should override the extension specific settings. Memoised so the
	// options object keeps a stable reference across cart ticks and only
	// rebuilds when the styling or the express method changes.
	const checkoutElementOptions = useMemo( () => {
		const withBlockOverride = {
			...buttonOptions,
			...( typeof buttonAttributes !== 'undefined'
				? { buttonHeight: Number( buttonAttributes.height ) }
				: {} ),
		};

		return {
			...adjustButtonHeights( withBlockOverride, expressPaymentMethod ),
			...getPaymentMethodsOverride( expressPaymentMethod ),
		};
	}, [ buttonOptions, buttonAttributes, expressPaymentMethod ] );

	return (
		<ExpressCheckoutElement
			options={ checkoutElementOptions }
			onClick={ onButtonClick }
			onConfirm={ onConfirm }
			onReady={ onElementsReady }
			onCancel={ onCancel }
			onShippingAddressChange={ onShippingAddressChange }
			onShippingRateChange={ onShippingRateChange }
		/>
	);
};

export default ExpressCheckoutComponent;
