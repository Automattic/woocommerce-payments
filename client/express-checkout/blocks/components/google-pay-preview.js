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
	const isScriptLoaded = useRef( false );

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

	const buttonHeight = Math.min(
		Math.max( buttonAttributes?.height ?? styleSettings.buttonHeight, 40 ),
		55
	);
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
		if ( ! googlePayContainerRef.current ) {
			return;
		}

		const container = googlePayContainerRef.current;

		const loadGooglePayButton = async () => {
			// The container may be inside an iframe, so we need to retrieve a reference to the document and window objects.
			const targetDocument = container.ownerDocument;
			const targetWindow = targetDocument.defaultView;

			if (
				! isScriptLoaded.current &&
				! targetWindow.google?.payments?.api?.PaymentsClient
			) {
				await new Promise( ( resolve, reject ) => {
					const script = targetDocument.createElement( 'script' );
					script.src = 'https://pay.google.com/gp/p/js/pay.js';
					script.onload = resolve;
					script.onerror = () =>
						reject(
							new Error( 'Failed to load Google Pay script.' )
						);
					targetDocument.head.appendChild( script );
				} );
				isScriptLoaded.current = true;
			}

			const googlePayClient = new targetWindow.google.payments.api.PaymentsClient(
				{
					environment: 'TEST',
				}
			);

			const buttonColor = theme === 'black' ? 'black' : 'white'; // There is no 'outline' theme in Google Pay.

			// Clear the existing button before creating a new one.
			container.innerHTML = '';

			const button = googlePayClient.createButton( {
				buttonType,
				buttonColor,
				buttonRadius: parseFloat( borderRadius ) || 0,
				buttonSizeMode: 'fill',
				onClick: () => {},
			} );
			container.appendChild( button );
		};

		// Fail gracefully if script fails to load - just don't render the button.
		loadGooglePayButton().catch( () => {} );
	}, [ theme, borderRadius, buttonType ] );

	return (
		<div
			ref={ googlePayContainerRef }
			id="express-checkout-button-preview-googlePay"
			style={ containerStyle }
		/>
	);
};

export default GooglePayPreview;
