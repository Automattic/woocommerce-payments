/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import { getPaymentMethodSettingsUrl } from '../../utils';
import ApplePay from 'assets/images/cards/apple-pay.svg?asset';
import GooglePay from 'assets/images/cards/google-pay.svg?asset';
import { usePaymentRequestEnabledSettings } from 'wcpay/data';

const AppleGooglePayExpressCheckoutItem = () => {
	const [
		isPaymentRequestEnabled,
		updateIsPaymentRequestEnabled,
	] = usePaymentRequestEnabledSettings();

	return (
		<li
			className="express-checkout"
			id="express-checkouts-apple-google-pay"
		>
			<div className="express-checkout__row">
				<div className="express-checkout__checkbox">
					<CheckboxControl
						label={ __(
							'Apple Pay / Google Pay',
							'woocommerce-payments'
						) }
						checked={ isPaymentRequestEnabled }
						onChange={ updateIsPaymentRequestEnabled }
					/>
				</div>
				<div>
					<div className="express-checkout__subgroup">
						<div className="express-checkout__icon">
							<img src={ ApplePay } alt="Apple Pay" />
						</div>
						<div className="express-checkout__label-container">
							<div className="express-checkout__label">
								{ __( 'Apple Pay', 'woocommerce-payments' ) }
							</div>
							<div className="express-checkout__description">
								{
									/* eslint-disable jsx-a11y/anchor-has-content */
									isPaymentRequestEnabled
										? __(
												'Apple Pay is an easy and secure way for customers to pay on your store. ',
												'woocommerce-payments'
										  )
										: interpolateComponents( {
												mixedString: __(
													/* eslint-disable-next-line max-len */
													'Apple Pay is an easy and secure way for customers to pay on your store. {{br/}}' +
														/* eslint-disable-next-line max-len */
														'By enabling this feature, you agree to {{stripeLink}}Stripe{{/stripeLink}} and' +
														"{{appleLink}} Apple{{/appleLink}}'s terms of use.",
													'woocommerce-payments'
												),
												components: {
													stripeLink: (
														<a
															target="_blank"
															rel="noreferrer"
															href="https://stripe.com/apple-pay/legal"
														/>
													),
													appleLink: (
														<a
															target="_blank"
															rel="noreferrer"
															/* eslint-disable-next-line max-len */
															href="https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/"
														/>
													),
													br: <br />,
												},
										  } )
									/* eslint-enable jsx-a11y/anchor-has-content */
								}
							</div>
						</div>
					</div>
					<div className="express-checkout__subgroup">
						<div className="express-checkout__icon">
							<img src={ GooglePay } alt="Google Pay" />
						</div>
						<div className="express-checkout__label-container">
							<div className="express-checkout__label">
								{ __( 'Google Pay', 'woocommerce-payments' ) }
							</div>
							<div className="express-checkout__description">
								{
									/* eslint-disable jsx-a11y/anchor-has-content */
									isPaymentRequestEnabled
										? __(
												'Offer customers a fast, secure checkout experience with Google Pay. ',
												'woocommerce-payments'
										  )
										: interpolateComponents( {
												mixedString: __(
													/* eslint-disable-next-line max-len */
													'Offer customers a fast, secure checkout experience with Google Pay. {{br/}}' +
														/* eslint-disable-next-line max-len */
														'By enabling this feature, you agree to {{stripeLink}}Stripe{{/stripeLink}}, ' +
														"and {{googleLink}}Google{{/googleLink}}'s terms of use.",
													'woocommerce-payments'
												),
												components: {
													stripeLink: (
														<a
															target="_blank"
															rel="noreferrer"
															href="https://stripe.com/apple-pay/legal"
														/>
													),
													googleLink: (
														<a
															target="_blank"
															rel="noreferrer"
															href="https://androidpay.developers.google.com/terms/sellertos"
														/>
													),
													br: <br />,
												},
										  } )
									/* eslint-enable jsx-a11y/anchor-has-content */
								}
							</div>
						</div>
					</div>
				</div>
				<div className="express-checkout__link">
					<a
						href={ getPaymentMethodSettingsUrl(
							'payment_request'
						) }
					>
						{ __( 'Customize', 'woocommerce-payments' ) }
					</a>
				</div>
			</div>
		</li>
	);
};

export default AppleGooglePayExpressCheckoutItem;
