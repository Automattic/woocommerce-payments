/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import React, { useContext } from 'react';
import { Button, CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { getPaymentMethodSettingsUrl } from '../../utils';
import { usePaymentRequestEnabledSettings } from 'wcpay/data';
import DuplicateNotice from 'wcpay/components/duplicate-notice';
import DuplicatedPaymentMethodsContext from '../settings-manager/duplicated-payment-methods-context';
import GooglePayTestModeCompatibilityNotice from '../google-pay-test-mode-compatibility-notice';
import methodsConfiguration from '../../payment-methods-map';

const AppleGooglePayExpressCheckoutItem = (): React.ReactElement => {
	const id = 'apple_pay_google_pay';

	const [
		isPaymentRequestEnabled,
		updateIsPaymentRequestEnabled,
	] = usePaymentRequestEnabledSettings();

	const {
		duplicates,
		dismissedDuplicateNotices,
		setDismissedDuplicateNotices,
	} = useContext( DuplicatedPaymentMethodsContext );
	const isDuplicate = Object.keys( duplicates ).includes( id );

	const { icon: ApplePayIcon } = methodsConfiguration.apple_pay;
	const { icon: GooglePayIcon } = methodsConfiguration.google_pay;

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
						__nextHasNoMarginBottom
					/>
				</div>
				<div className="express-checkout__text-container">
					<div>
						<div className="express-checkout__subgroup">
							<div className="express-checkout__icon">
								<ApplePayIcon />
							</div>
							<div className="express-checkout__label express-checkout__label-mobile">
								{ methodsConfiguration.apple_pay.label }
							</div>
							<div className="express-checkout__label-container">
								<div className="express-checkout__label express-checkout__label-desktop">
									{ methodsConfiguration.apple_pay.label }
								</div>
								<div className="express-checkout__description">
									{
										methodsConfiguration.apple_pay
											.description
									}
									{ isPaymentRequestEnabled && ' ' }
									{
										/* eslint-disable jsx-a11y/anchor-has-content */
										isPaymentRequestEnabled &&
											interpolateComponents( {
												mixedString: __(
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
								<GooglePayIcon />
							</div>
							<div className="express-checkout__label express-checkout__label-mobile">
								{ methodsConfiguration.google_pay.label }
							</div>
							<div className="express-checkout__label-container">
								<div className="express-checkout__label express-checkout__label-desktop">
									{ methodsConfiguration.google_pay.label }
								</div>
								<div className="express-checkout__description">
									{
										methodsConfiguration.google_pay
											.description
									}
									{ isPaymentRequestEnabled && ' ' }
									{
										/* eslint-disable jsx-a11y/anchor-has-content */
										isPaymentRequestEnabled &&
											interpolateComponents( {
												mixedString: __(
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
						<Button
							href={ getPaymentMethodSettingsUrl(
								'payment_request'
							) }
							isSecondary
						>
							{ __( 'Customize', 'woocommerce-payments' ) }
						</Button>
					</div>
				</div>
			</div>
			<GooglePayTestModeCompatibilityNotice />
			{ isDuplicate && (
				<DuplicateNotice
					paymentMethod={ id }
					gatewaysEnablingPaymentMethod={ duplicates[ id ] }
					dismissedNotices={ dismissedDuplicateNotices }
					setDismissedDuplicateNotices={
						setDismissedDuplicateNotices
					}
				/>
			) }
		</li>
	);
};

export default AppleGooglePayExpressCheckoutItem;
