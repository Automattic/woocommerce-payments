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
	// which should override the extension specific settings. Keyed on the button
	// height primitive (not the fresh `buttonAttributes` object) so the options
	// keep a stable reference and only rebuild when the styling or the express
	// method actually changes.
	const hasButtonAttributes = typeof buttonAttributes !== 'undefined';
	const buttonHeight = buttonAttributes?.height;
	const checkoutElementOptions = useMemo( () => {
		const withBlockOverride = {
			...buttonOptions,
			...( hasButtonAttributes
				? { buttonHeight: Number( buttonHeight ) }
				: {} ),
		};

		return {
			...adjustButtonHeights( withBlockOverride, expressPaymentMethod ),
			...getPaymentMethodsOverride( expressPaymentMethod ),
		};
	}, [
		buttonOptions,
		hasButtonAttributes,
		buttonHeight,
		expressPaymentMethod,
	] );

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
