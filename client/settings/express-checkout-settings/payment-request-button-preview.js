/** @format */

/**
 * External dependencies
 */
import { React, useState, useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	PaymentRequestButtonElement,
	useStripe,
} from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import { shouldUseGooglePayBrand } from 'payment-request/utils';
import BannerNotice from 'components/banner-notice';
import { WoopayExpressCheckoutButton } from 'wcpay/checkout/woopay/express-button/woopay-express-checkout-button';
import {
	usePaymentRequestButtonSize,
	usePaymentRequestButtonTheme,
	usePaymentRequestButtonType,
	usePaymentRequestEnabledSettings,
	useWooPayEnabledSettings,
} from '../../data';

/**
 * stripePromise is used to pass into <Elements>'s stripe props.
 * The stripe prop in <Elements> can't be change once passed in.
 * Keeping this outside of <PaymentRequestButtonPreview> so that
 * re-rendering does not change it.
 */

const BrowserHelpText = () => {
	let browser = 'Google Chrome';
	let paymentMethodName = 'Google Pay';

	if ( shouldUseGooglePayBrand() ) {
		browser = 'Safari';
		paymentMethodName = 'Apple Pay';
	}

	return (
		<p className="payment-method-settings__preview-help-text">
			{ sprintf(
				__(
					/* translators: %1: Payment method name %2: Browser name. */
					'To preview the %1$s button, view this page in the %2$s browser.',
					'woocommerce-payments'
				),
				paymentMethodName,
				browser
			) }
		</p>
	);
};

const buttonSizeToPxMap = {
	default: 40,
	medium: 48,
	large: 56,
};

const PaymentRequestButtonPreview = () => {
	const stripe = useStripe();
	const [ paymentRequest, setPaymentRequest ] = useState();
	const [ isLoading, setIsLoading ] = useState( true );
	const [ buttonType ] = usePaymentRequestButtonType();
	const [ size ] = usePaymentRequestButtonSize();
	const [ theme ] = usePaymentRequestButtonTheme();
	const [ isWooPayEnabled ] = useWooPayEnabledSettings();
	const [ isPaymentRequestEnabled ] = usePaymentRequestEnabledSettings();

	useEffect( () => {
		if ( ! stripe ) {
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
	}, [ stripe, setPaymentRequest, setIsLoading ] );

	/**
	 * If stripe is loading, then display nothing.
	 * If stripe finished loading but payment request button failed to load (null), display info section.
	 * If stripe finished loading and payment request button loads, display the button.
	 */

	return (
		<>
			{ ( isWooPayEnabled ||
				( isPaymentRequestEnabled && paymentRequest ) ) && (
				<div
					className="payment-method-settings__preview"
					data-theme={ theme }
				>
					{ isWooPayEnabled && (
						<WoopayExpressCheckoutButton
							isPreview={ true }
							buttonSettings={ {
								type: buttonType,
								text: 'Buy',
								theme: theme,
								height: `${
									buttonSizeToPxMap[ size ] ||
									buttonSizeToPxMap.default
								}px`,
								size,
							} }
						/>
					) }
					{ isPaymentRequestEnabled &&
						! isLoading &&
						paymentRequest && (
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
												buttonSizeToPxMap.default
											}px`,
										},
									},
								} }
							/>
						) }
				</div>
			) }
			{ ! isWooPayEnabled && ! isPaymentRequestEnabled && (
				<BannerNotice icon status="info" isDismissible={ false }>
					{ __(
						'To preview the express checkout buttons, ' +
							'activate at least one express checkout.',
						'woocommerce-payments'
					) }
				</BannerNotice>
			) }
			{ isPaymentRequestEnabled && ! isLoading && ! paymentRequest && (
				<BannerNotice icon status="info" isDismissible={ false }>
					{ __(
						'To preview the Apple Pay and Google Pay buttons, ' +
							'ensure your device is configured to accept Apple Pay or Google Pay, ' +
							'and view this page using the Safari or Chrome browsers.',
						'woocommerce-payments'
					) }
				</BannerNotice>
			) }
			<BrowserHelpText />
		</>
	);
};

export default PaymentRequestButtonPreview;
