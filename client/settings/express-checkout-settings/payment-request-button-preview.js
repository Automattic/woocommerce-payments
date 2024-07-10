/** @format */

/**
 * External dependencies
 */
import { React, useState, useEffect, useContext } from 'react';
import { __ } from '@wordpress/i18n';
import {
	PaymentRequestButtonElement,
	useStripe,
} from '@stripe/react-stripe-js';

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
import WCPaySettingsContext from '../wcpay-settings-context';
import { ExpressCheckoutPreviewComponent } from 'wcpay/express-checkout/blocks/components/express-checkout-preview';

const buttonSizeToPxMap = {
	small: 40,
	medium: 48,
	large: 56,
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
			radius,
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
	const [ paymentRequest, setPaymentRequest ] = useState();
	const [ isLoading, setIsLoading ] = useState( true );
	const [ buttonType ] = usePaymentRequestButtonType();
	const [ size ] = usePaymentRequestButtonSize();
	const [ theme ] = usePaymentRequestButtonTheme();
	const [ radius ] = usePaymentRequestButtonBorderRadius();
	const [ isWooPayEnabled ] = useWooPayEnabledSettings();
	const [ isPaymentRequestEnabled ] = usePaymentRequestEnabledSettings();

	const {
		featureFlags: { isStripeEceEnabled },
	} = useContext( WCPaySettingsContext );

	useEffect( () => {
		if ( ! stripe ) {
			return;
		}

		// We don't need a payment request when using the ECE buttons.
		if ( isStripeEceEnabled ) {
			setIsLoading( false );
			return;
		}

		// Create a preview for payment button. The label and its total are placeholders.
		const stripePaymentRequest = stripe.paymentRequest( {
			country: 'US',
			currency: 'usd',
			total: {
				label: __( 'Total', 'woocommerce-payments' ),
				amount: 99,
			},
			requestPayerName: true,
			requestPayerEmail: true,
		} );

		// Check the availability of the Payment Request API.
		stripePaymentRequest.canMakePayment().then( ( result ) => {
			if ( result ) {
				setPaymentRequest( stripePaymentRequest );
			}
			setIsLoading( false );
		} );
	}, [ stripe, setPaymentRequest, setIsLoading, isStripeEceEnabled ] );

	/**
	 * If stripe is loading, then display nothing.
	 * If stripe finished loading but payment request button failed to load (null), display info section.
	 * If stripe finished loading and payment request button loads, display the button.
	 */

	// No need to check `isStripeEceEnabled` since that's not what controls whether the express checkout
	// buttons are displayed or not, that's always controlled by `isPaymentRequestEnabled`.
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

	const woopayPreview = isWooPayEnabled ? (
		<WooPayButtonPreview
			size={ size }
			buttonType={ buttonType }
			theme={ theme }
			radius={ radius }
		/>
	) : null;

	const isHttpsEnabled = window.location.protocol === 'https:';

	const expressCheckoutButtonPreview =
		isPaymentRequestEnabled && isStripeEceEnabled
			? ( isHttpsEnabled && (
					<ExpressCheckoutPreviewComponent
						stripe={ stripe }
						buttonType={ buttonType }
						theme={ theme }
						height={
							buttonSizeToPxMap[ size ] ||
							buttonSizeToPxMap.medium
						}
						radius={ radius }
					/>
			  ) ) || <PreviewRequirementsNotice />
			: null;

	const prbButtonPreview =
		isPaymentRequestEnabled && paymentRequest && ! isLoading
			? ( isHttpsEnabled && (
					<PaymentRequestButtonElement
						key={ `${ buttonType }-${ theme }-${ size }` }
						onClick={ ( e ) => {
							e.preventDefault();
						} }
						options={ {
							paymentRequest: paymentRequest,
							style: {
								paymentRequestButton: {
									type: buttonType,
									theme: theme,
									height: `${
										buttonSizeToPxMap[ size ] ||
										buttonSizeToPxMap.medium
									}px`,
								},
							},
						} }
					/>
			  ) ) || <PreviewRequirementsNotice />
			: null;

	if ( woopayPreview || expressCheckoutButtonPreview || prbButtonPreview ) {
		return (
			<ButtonPreviewWrapper theme={ theme }>
				{ woopayPreview }
				{ /* We never want to show both ECE and PRB previews at the same time. */ }
				{ expressCheckoutButtonPreview || prbButtonPreview }
			</ButtonPreviewWrapper>
		);
	}

	return <PreviewRequirementsNotice />;
};

export default PaymentRequestButtonPreview;
