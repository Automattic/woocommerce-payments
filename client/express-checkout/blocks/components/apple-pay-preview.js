/**
 * External dependencies
 */
import { useMemo } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutButtonStyleSettings,
} from '../../utils';

const ApplePayPreview = ( { buttonAttributes } ) => {
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
	const theme = styleSettings.buttonTheme?.applePay ?? 'black';
	const buttonType = styleSettings.buttonType?.applePay ?? 'plain';

	const buttonStyle = useMemo( () => {
		const style = {
			height: `${ buttonHeight }px`,
			borderRadius,
			ApplePayButtonType: buttonType,
			WebkitAppearance: '-apple-pay-button',
			width: '100%',
		};

		if ( [ 'black', 'white', 'white-outline' ].includes( theme ) ) {
			style.ApplePayButtonStyle = theme;
		} else {
			style.ApplePayButtonStyle = 'white';
		}

		return style;
	}, [ buttonHeight, borderRadius, buttonType, theme ] );

	return (
		<div>
			<button
				type="button"
				id="express-checkout-button-preview-applePay"
				className="express-checkout-button-preview"
				style={ buttonStyle }
				aria-label={ __( 'Apple Pay', 'woocommerce-payments' ) }
			/>
		</div>
	);
};

export default ApplePayPreview;
