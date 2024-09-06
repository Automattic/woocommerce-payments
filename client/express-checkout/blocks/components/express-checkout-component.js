/**
 * External dependencies
 */
import { ExpressCheckoutElement } from '@stripe/react-stripe-js';
/**
 * Internal dependencies
 */
import {
	shippingAddressChangeHandler,
	shippingRateChangeHandler,
} from '../../event-handlers';
import { useExpressCheckout } from '../hooks/use-express-checkout';
import { PAYMENT_METHOD_NAME_EXPRESS_CHECKOUT_ELEMENT } from 'wcpay/checkout/constants';

const getPaymentMethodsOverride = ( enabledPaymentMethod ) => {
	const allDisabled = {
		amazonPay: 'never',
		applePay: 'never',
		googlePay: 'never',
		link: 'never',
		paypal: 'never',
	};

	const enabledParam = [ 'applePay', 'googlePay' ].includes(
		enabledPaymentMethod
	)
		? 'always'
		: 'auto';

	return {
		paymentMethods: {
			...allDisabled,
			[ enabledPaymentMethod ]: enabledParam,
		},
	};
};

// Visual adjustments to horizontally align the buttons.
const adjustButtonHeights = ( buttonOptions, expressPaymentMethod ) => {
	// Apple Pay has a nearly imperceptible height difference. We increase it by 1px here.
	if ( buttonOptions.buttonTheme.applePay === 'black' ) {
		if ( expressPaymentMethod === 'applePay' ) {
			buttonOptions.buttonHeight = buttonOptions.buttonHeight + 0.4;
		}
	}

	// GooglePay with the white theme has a 2px height difference due to its border.
	if (
		expressPaymentMethod === 'googlePay' &&
		buttonOptions.buttonTheme.googlePay === 'white'
	) {
		buttonOptions.buttonHeight = buttonOptions.buttonHeight - 2;
	}

	// Clamp the button height to the allowed range 40px to 55px.
	buttonOptions.buttonHeight = Math.max(
		40,
		Math.min( buttonOptions.buttonHeight, 55 )
	);
	return buttonOptions;
};

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
	} );

	const onShippingAddressChange = ( event ) =>
		shippingAddressChangeHandler( api, event, elements );

	const onShippingRateChange = ( event ) =>
		shippingRateChangeHandler( api, event, elements );

	const onElementsReady = ( event ) => {
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
	};

	return (
		<ExpressCheckoutElement
			options={ {
				...adjustButtonHeights( buttonOptions, expressPaymentMethod ),
				...getPaymentMethodsOverride( expressPaymentMethod ),
			} }
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
