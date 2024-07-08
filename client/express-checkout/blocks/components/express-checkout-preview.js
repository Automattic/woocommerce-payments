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
import { getDefaultBorderRadius } from 'wcpay/utils/express-checkout';

export const ExpressCheckoutPreviewComponent = ( {
	stripe,
	buttonType,
	theme,
	height,
	radius,
} ) => {
	const [ canRenderButtons, setCanRenderButtons ] = useState( true );

	const options = {
		mode: 'payment',
		amount: 1000,
		currency: 'usd',
		appearance: {
			variables: {
				borderRadius: `${ radius ?? getDefaultBorderRadius() }px`,
			},
		},
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

	const type = buttonType === 'default' ? 'plain' : buttonType;

	const buttonOptions = {
		buttonHeight: Math.min( Math.max( height, 40 ), 55 ),
		buttonTheme: {
			googlePay: mapThemeConfigToButtonTheme( 'googlePay', theme ),
			applePay: mapThemeConfigToButtonTheme( 'applePay', theme ),
		},
		buttonType: {
			googlePay: type,
			applePay: type,
		},
		paymentMethods: {
			link: 'never',
			googlePay: 'always',
			applePay: 'always',
		},
		layout: { overflow: 'never' },
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
				key={ `${ buttonType }-${ theme }` }
				style={ { minHeight: `${ height }px`, width: '100%' } }
			>
				<Elements stripe={ stripe } options={ options }>
					<ExpressCheckoutElement
						options={ buttonOptions }
						onClick={ () => {} }
						onReady={ onReady }
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