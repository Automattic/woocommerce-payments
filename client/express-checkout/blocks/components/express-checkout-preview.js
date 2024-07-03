/**
 * External dependencies
 */
import { useState } from 'react';
import { Elements, ExpressCheckoutElement } from '@stripe/react-stripe-js';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import InlineNotice from 'components/inline-notice';

export const ExpressCheckoutPreviewComponent = ( {
	stripe,
	buttonType,
	theme,
	height,
} ) => {
	const [ canRenderButtons, setCanRenderButtons ] = useState( true );

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
		layout: { maxColumns: 1, overflow: 'never' },
	};

	const onReady = ( { availablePaymentMethods } ) => {
		if ( availablePaymentMethods ) {
			setCanRenderButtons( true );
		} else {
			setCanRenderButtons( false );
		}
	};

	if ( canRenderButtons ) {
		return (
			<div
				key={ `${ buttonType }-${ height }-${ theme }` }
				style={ { minHeight: `${ height }px`, width: '100%' } }
			>
				<Elements stripe={ stripe } options={ options }>
					<ExpressCheckoutElement
						options={ buttonOptions }
						onClick={ () => {} }
						onReady={ onReady }
						// onConfirm={ onConfirm }
						// onCancel={ onCancel }
					/>
				</Elements>
			</div>
		);
	}

	return (
		<InlineNotice icon status="error" isDismissible={ false }>
			{ __(
				'Failed to preview the Apple Pay or Google Pay button. ' +
					'Please ensure your store is served over HTTPS on a domain available to the public internet, ' +
					'your device is configured to use Apple Pay or Google Pay, ' +
					'and view this page using the Safari or Chrome browsers.',
				'woocommerce-payments'
			) }
		</InlineNotice>
	);
};
