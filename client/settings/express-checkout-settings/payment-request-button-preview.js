/** @format */

/**
 * External dependencies
 */
import { React } from 'react';
import { __ } from '@wordpress/i18n';
import { useStripe } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import InlineNotice from 'components/inline-notice';
import { WoopayExpressCheckoutButton } from 'wcpay/checkout/woopay/express-button/woopay-express-checkout-button';
import {
	usePaymentRequestButtonSize,
	usePaymentRequestButtonTheme,
	usePaymentRequestButtonType,
	usePaymentRequestButtonBorderRadius,
	usePaymentRequestEnabledSettings,
	useWooPayEnabledSettings,
} from '../../data';
import { ExpressCheckoutPreviewComponent } from 'wcpay/express-checkout/blocks/components/express-checkout-preview';

const buttonSizeToPxMap = {
	small: 40,
	medium: 48,
	large: 55,
};

const WooPayButtonPreview = ( { size, buttonType, theme, radius } ) => (
	<WoopayExpressCheckoutButton
		isPreview={ true }
		buttonSettings={ {
			type: buttonType,
			text: 'Buy',
			theme: theme,
			height: `${
				buttonSizeToPxMap[ size ] || buttonSizeToPxMap.medium
			}px`,
			size,
		} }
		buttonAttributes={ {
			height: buttonSizeToPxMap[ size ] || buttonSizeToPxMap.medium,
			borderRadius: radius,
		} }
	/>
);

const ButtonPreviewWrapper = ( { theme, children } ) => (
	<>
		<div className="payment-method-settings__preview" data-theme={ theme }>
			{ children }
		</div>
	</>
);

const PreviewRequirementsNotice = () => (
	<InlineNotice icon status="info" isDismissible={ false }>
		{ __(
			'To preview the express checkout buttons, ' +
				'ensure your store uses HTTPS on a publicly available domain, ' +
				"and you're viewing this page in a Safari or Chrome browser. " +
				'Your device must be configured to use Apple Pay or Google Pay.',
			'woocommerce-payments'
		) }
	</InlineNotice>
);

const PaymentRequestButtonPreview = () => {
	const stripe = useStripe();
	const [ buttonType ] = usePaymentRequestButtonType();
	const [ size ] = usePaymentRequestButtonSize();
	const [ theme ] = usePaymentRequestButtonTheme();
	const [ radius ] = usePaymentRequestButtonBorderRadius();
	const [ isWooPayEnabled ] = useWooPayEnabledSettings();
	const [ isPaymentRequestEnabled ] = usePaymentRequestEnabledSettings();

	if ( ! isWooPayEnabled && ! isPaymentRequestEnabled ) {
		return (
			<InlineNotice icon status="info" isDismissible={ false }>
				{ __(
					'To preview the express checkout buttons, ' +
						'activate at least one express checkout.',
					'woocommerce-payments'
				) }
			</InlineNotice>
		);
	}

	/**
	 * If stripe is loading, then display nothing.
	 * If stripe finished loading but payment request button failed to load (null), display info section.
	 * If stripe finished loading and payment request button loads, display the button.
	 */

	const woopayPreview = isWooPayEnabled ? (
		<WooPayButtonPreview
			size={ size }
			buttonType={ buttonType }
			theme={ theme }
			radius={ radius }
		/>
	) : null;

	const isHttpsEnabled = window.location.protocol === 'https:';

	const expressCheckoutButtonPreview = isPaymentRequestEnabled
		? ( isHttpsEnabled && (
				<ExpressCheckoutPreviewComponent
					stripe={ stripe }
					buttonType={ buttonType }
					theme={ theme }
					height={
						buttonSizeToPxMap[ size ] || buttonSizeToPxMap.medium
					}
					radius={ radius }
				/>
		  ) ) || <PreviewRequirementsNotice />
		: null;

	if ( woopayPreview || expressCheckoutButtonPreview ) {
		return (
			<ButtonPreviewWrapper theme={ theme }>
				{ woopayPreview }
				{ expressCheckoutButtonPreview }
			</ButtonPreviewWrapper>
		);
	}

	return <PreviewRequirementsNotice />;
};

export default PaymentRequestButtonPreview;
