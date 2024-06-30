/**
 * External dependencies
 */
import { Elements, ExpressCheckoutElement } from '@stripe/react-stripe-js';

export const ExpressCheckoutPreviewComponent = ( {
	stripe,
	buttonType,
	theme,
	height,
} ) => {
	const options = {
		mode: 'payment',
		amount: 1000,
		currency: 'usd',
	};

	const mapThemeConfigToButtonTheme = ( paymentMethod, buttonTheme ) => {
		switch ( buttonTheme ) {
			case 'dark':
				return 'black';
			case 'light':
				return 'white';
			case 'light-outline':
				if ( paymentMethod === 'googlePay' ) {
					return 'white';
				}

				return 'white-outline';
			default:
				return 'black';
		}
	};

	const googlePayType = buttonType === 'default' ? 'plain' : buttonType;

	const applePayType = buttonType === 'default' ? 'plain' : buttonType;

	const buttonOptions = {
		buttonHeight: Math.min( Math.max( height, 40 ), 55 ),
		buttonTheme: {
			googlePay: mapThemeConfigToButtonTheme( 'googlePay', theme ),
			applePay: mapThemeConfigToButtonTheme( 'applePay', theme ),
		},
		buttonType: {
			googlePay: googlePayType,
			applePay: applePayType,
		},
		paymentMethods: {
			link: 'never',
			googlePay: 'always',
			applePay: 'always',
		},
	};

	return (
		<div
			key={ `${ buttonType }-${ height }-${ theme }` }
			style={ { minHeight: `${ height }px`, width: '100%' } }
		>
			<Elements stripe={ stripe } options={ options }>
				<ExpressCheckoutElement
					options={ buttonOptions }
					onClick={ () => {} }
					// onConfirm={ onConfirm }
					// onCancel={ onCancel }
				/>
			</Elements>
		</div>
	);
};
