/**
 * External dependencies
 */
import { ExpressCheckoutElement } from '@stripe/react-stripe-js';
import { select } from '@wordpress/data';
import { useMemo } from 'react';
/**
 * Internal dependencies
 */
import {
	shippingAddressChangeHandler,
	shippingRateChangeHandler,
} from '../../event-handlers';
import { useExpressCheckout } from '../hooks/use-express-checkout';
import { WC_STORE_CART } from 'wcpay/checkout/constants';
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
	const onShippingAddressChange = ( event ) =>
		shippingAddressChangeHandler( event, elements, setExpressPaymentError );

	const onShippingRateChange = ( event ) =>
		shippingRateChangeHandler(
			event,
			elements,
			select( WC_STORE_CART )?.getCartData(),
			setExpressPaymentError
		);

	// The Cart & Checkout blocks provide unified styles across all buttons,
	// which should override the extension specific settings. Blocks passes a
	// new `buttonAttributes` object on every render, so key on its height.
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
			onReady={ onReady }
			onCancel={ onCancel }
			onShippingAddressChange={ onShippingAddressChange }
			onShippingRateChange={ onShippingRateChange }
		/>
	);
};

export default ExpressCheckoutComponent;
