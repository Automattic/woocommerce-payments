/**
 * External dependencies
 */
import { ExpressCheckoutElement } from '@stripe/react-stripe-js';
import { select } from '@wordpress/data';
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
	onAvailabilityChange,
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

	const onElementsReady = ( event ) => {
		// Let the container hide itself rather than removing the block's slot: the
		// block keeps it mounted across cart updates, so a removed slot never returns.
		onAvailabilityChange(
			!! event.availablePaymentMethods?.[ expressPaymentMethod ]
		);

		// Any actions that WooPayments needs to perform.
		onReady( event );
	};

	// The Cart & Checkout blocks provide unified styles across all buttons,
	// which should override the extension specific settings.
	const withBlockOverride = () => {
		const override = {};
		if ( typeof buttonAttributes !== 'undefined' ) {
			override.buttonHeight = Number( buttonAttributes.height );
		}
		return {
			...buttonOptions,
			...override,
		};
	};

	const checkoutElementOptions = {
		...withBlockOverride(),
		...adjustButtonHeights( withBlockOverride(), expressPaymentMethod ),
		...getPaymentMethodsOverride( expressPaymentMethod ),
	};

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
