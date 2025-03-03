/**
 * External dependencies
 */
import { useEffect, useRef } from 'react';

/**
 * Internal dependencies
 */
import { getExpressCheckoutButtonAppearance } from '../../utils';

export const SUPPORTED_PREVIEW_PAYMENT_METHODS = [ 'googlePay', 'applePay' ];

const GooglePayButtonPreview = ( { options, buttonAttributes, theme } ) => {
	const googlePlayContainerRef = useRef( null );
	const hasStartedLoadingGooglePlayButton = useRef( null );
	const appearance = getExpressCheckoutButtonAppearance( buttonAttributes );
	const borderRadius = appearance.variables.borderRadius;

	useEffect( () => {
		if (
			googlePlayContainerRef.current &&
			! hasStartedLoadingGooglePlayButton.current
		) {
			hasStartedLoadingGooglePlayButton.current = true;
			( async () => {
				// The container may be inside an iframe, so we need to retrieve a reference to the document and window objects.
				const targetDocument =
					googlePlayContainerRef.current.ownerDocument;
				const targetWindow = targetDocument.defaultView;
				if ( ! targetWindow.google?.payments?.api?.PaymentsClient ) {
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
					buttonType: options.buttonType.googlePay,
					buttonColor,
					buttonRadius: parseFloat( borderRadius ),
					buttonSizeMode: 'fill',
					onClick: () => {},
				} );
				googlePlayContainerRef.current.appendChild( button );
			} )();
		}
	}, [ theme, borderRadius, options.buttonType.googlePay ] );

	useEffect( () => {
		googlePlayContainerRef.current
			?.querySelector( 'button' )
			?.style?.setProperty( 'border-radius', borderRadius );
	}, [ borderRadius ] );

	return (
		<div
			ref={ googlePlayContainerRef }
			id="express-checkout-button-preview-googlePay"
			style={ {
				height: `${ options.buttonHeight }px`,
				width: '100%',
			} }
		/>
	);
};

const ApplePayButtonPreview = ( { options, buttonAttributes, theme } ) => {
	const appearance = getExpressCheckoutButtonAppearance( buttonAttributes );
	const borderRadius = appearance.variables.borderRadius;

	const buttonStyle = {
		height: `${ options.buttonHeight }px`,
		borderRadius,
		ApplePayButtonType: options.buttonType.applePay,
		WebkitAppearance: '-apple-pay-button',
		width: '100%',
	};

	if ( [ 'black', 'white', 'white-outline' ].includes( theme ) ) {
		buttonStyle.ApplePayButtonStyle = theme;
	} else {
		buttonStyle.ApplePayButtonStyle = 'white';
	}

	return (
		<div>
			<button
				type="button"
				id="express-checkout-button-preview-applePay"
				className="express-checkout-button-preview"
				style={ buttonStyle }
			/>
		</div>
	);
};

const ExpressCheckoutButtonPreview = ( {
	expressPaymentMethod,
	options,
	buttonAttributes,
} ) => {
	const theme = options.buttonTheme[ expressPaymentMethod ];

	if ( expressPaymentMethod === 'googlePay' ) {
		return (
			<GooglePayButtonPreview
				options={ options }
				buttonAttributes={ buttonAttributes }
				theme={ theme }
			/>
		);
	}

	if ( expressPaymentMethod === 'applePay' ) {
		return (
			<ApplePayButtonPreview
				options={ options }
				buttonAttributes={ buttonAttributes }
				theme={ theme }
			/>
		);
	}

	return null;
};

export default ExpressCheckoutButtonPreview;
