/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import { getPaymentMethodSettingsUrl } from '../../utils';
import { usePaymentRequestEnabledSettings } from 'wcpay/data';
import CardBody from '../card-body';
import AppleGoogleIcon from '../../gateway-icons/apple-google';
import './style.scss';

const PaymentRequest = () => {
	const [
		isPaymentRequestEnabled,
		updateIsPaymentRequestEnabled,
	] = usePaymentRequestEnabledSettings();

	return (
		<Card className="payment-requests">
			<CardBody size={ 0 }>
				<ul className="payment-requests-list">
					<li className="payment-request has-icon-border">
						<div className="payment-request__checkbox">
							<CheckboxControl
								checked={ isPaymentRequestEnabled }
								onChange={ updateIsPaymentRequestEnabled }
							/>
						</div>
						<div className="payment-request__icon">
							<AppleGoogleIcon />
						</div>
						<div className="payment-method__label-container">
							<div className="payment-request__label">
								{ __(
									'Apple Pay / Google Pay',
									'woocommerce-payments'
								) }
							</div>
							<div className="payment-method__description">
								{
									/* eslint-disable jsx-a11y/anchor-has-content */
									interpolateComponents( {
										mixedString: __(
											'By enabling this feature, you agree to {{stripeLink}}Stripe{{/stripeLink}}, ' +
												"{{appleLink}}Apple{{/appleLink}}, and {{googleLink}}Google{{/googleLink}}'s terms of use.",
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
													href="https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/"
												/>
											),
											googleLink: (
												<a
													target="_blank"
													rel="noreferrer"
													href="https://androidpay.developers.google.com/terms/sellertos"
												/>
											),
										},
									} )
									/* eslint-enable jsx-a11y/anchor-has-content */
								}
							</div>
						</div>
						<div className="payment-request__link">
							<a
								href={ getPaymentMethodSettingsUrl(
									'payment_request'
								) }
							>
								{ __( 'Customize', 'woocommerce-payments' ) }
							</a>
						</div>
					</li>
				</ul>
			</CardBody>
		</Card>
	);
};

export default PaymentRequest;
