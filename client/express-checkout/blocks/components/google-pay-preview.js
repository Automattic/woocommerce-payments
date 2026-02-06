/**
 * External dependencies
 */
import { useEffect, useMemo, useRef } from 'react';

/**
 * Internal dependencies
 */
import {
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutButtonStyleSettings,
} from '../../utils';

const GooglePayPreview = ( { buttonAttributes } ) => {
	const googlePayContainerRef = useRef( null );
	const hasStartedLoadingGooglePayButton = useRef( null );

	const styleSettings = useMemo(
		() => getExpressCheckoutButtonStyleSettings(),
		[]
	);

	const borderRadius = useMemo( () => {
		const appearance = getExpressCheckoutButtonAppearance(
			buttonAttributes
		);
		return appearance.variables.borderRadius;
	}, [ buttonAttributes ] );

	const buttonHeight = buttonAttributes?.height ?? styleSettings.buttonHeight;
	const theme = styleSettings.buttonTheme?.googlePay ?? 'black';
	const buttonType = styleSettings.buttonType?.googlePay ?? 'plain';

	const containerStyle = useMemo(
		() => ( {
			height: `${ buttonHeight }px`,
			width: '100%',
			overflow: 'hidden',
		} ),
		[ buttonHeight ]
	);

	useEffect( () => {
		if (
			googlePayContainerRef.current &&
			! hasStartedLoadingGooglePayButton.current
		) {
			hasStartedLoadingGooglePayButton.current = true;
			( async () => {
				// The container may be inside an iframe, so we need to retrieve a reference to the document and window objects.
				const targetDocument =
					googlePayContainerRef.current.ownerDocument;
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
					buttonType,
					buttonColor,
					buttonRadius: parseFloat( borderRadius ),
					buttonSizeMode: 'fill',
					onClick: () => {},
				} );
				googlePayContainerRef.current.appendChild( button );
			} )();
		}
	}, [ theme, borderRadius, buttonType ] );

	useEffect( () => {
		const button = googlePayContainerRef.current?.querySelector( 'button' );
		if ( button ) {
			button.style.setProperty( 'border-radius', borderRadius );
			button.style.setProperty( 'width', '100%' );
			button.style.setProperty( 'height', '100%' );
		}
	}, [ borderRadius ] );

	return (
		<div
			ref={ googlePayContainerRef }
			id="express-checkout-button-preview-googlePay"
			style={ containerStyle }
		/>
	);
};

export default GooglePayPreview;
