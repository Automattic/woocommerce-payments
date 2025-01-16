/**
 * External dependencies
 */
import { useEffect, useMemo, useRef } from 'react';

/**
 * Internal dependencies
 */
import { getExpressCheckoutButtonAppearance } from 'wcpay/express-checkout/utils';

const ExpressCheckoutButtonPreview = ( {
	expressPaymentMethod,
	options,
	buttonAttributes,
} ) => {
	const appearance = useMemo(
		() => getExpressCheckoutButtonAppearance( buttonAttributes ),
		[ buttonAttributes ]
	);
	const ref = useRef( null );
	const renderGooglePayButtonPromise = useRef( null );

	const theme = options.buttonTheme[ expressPaymentMethod ];
	const borderRadius = appearance.variables.borderRadius;

	useEffect( () => {
		if (
			ref.current &&
			expressPaymentMethod === 'googlePay' &&
			! renderGooglePayButtonPromise.current
		) {
			renderGooglePayButtonPromise.current = ( async () => {
				const targetDocument = ref.current.ownerDocument;
				const targetWindow = targetDocument.defaultView;
				if ( ! targetWindow.googlePayClient ) {
					await new Promise( ( resolve ) => {
						const script = document.createElement( 'script' );
						script.src = 'https://pay.google.com/gp/p/js/pay.js';
						script.onload = resolve;
						targetDocument.head.appendChild( script );
					} );
				}

				const googlePayClient = new targetWindow.google.payments.api.PaymentsClient(
					{
						environment: 'TEST',
					}
				);

				const buttonColor = theme === 'black' ? 'black' : 'white'; // There is no 'outline' theme in Google Pay.

				const button = googlePayClient.createButton( {
					buttonType: 'plain',
					buttonColor,
					buttonRadius: parseFloat( borderRadius ),
					buttonSizeMode: 'fill',
					onClick: () => {},
				} );
				ref.current.appendChild( button );
			} )();
		}
	}, [ ref, theme, expressPaymentMethod, borderRadius ] );

	if ( expressPaymentMethod === 'googlePay' ) {
		return (
			<div
				ref={ ref }
				style={ {
					height: `${ options.buttonHeight }px`,
					width: '100%',
				} }
			/>
		);
	}

	const buttonStyle = {
		height: `${ options.buttonHeight }px`,
		borderRadius,
	};

	if ( expressPaymentMethod === 'applePay' ) {
		buttonStyle.WebkitAppearance = '-apple-pay-button';
		if ( theme === 'black' ) {
			buttonStyle.ApplePayButtonStyle = 'black';
		} else if ( theme === 'outline' ) {
			buttonStyle.ApplePayButtonStyle = 'white-outline';
		} else {
			buttonStyle.ApplePayButtonStyle = 'white';
		}

		return (
			<div>
				<button
					type="button"
					id={ `express-checkout-button-preview-${ expressPaymentMethod }` }
					className="express-checkout-button-preview"
					style={ buttonStyle }
				/>
			</div>
		);
	}

	return null;
};

export default ExpressCheckoutButtonPreview;
