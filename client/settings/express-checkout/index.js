/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { useContext } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getPaymentMethodSettingsUrl } from '../../utils';
import {
	usePaymentRequestEnabledSettings,
	usePlatformCheckoutEnabledSettings,
} from 'wcpay/data';
import CardBody from '../card-body';
import PaymentRequestIcon from '../../gateway-icons/payment-request';
import WooIcon from '../../gateway-icons/woo';
import './style.scss';
import WCPaySettingsContext from '../wcpay-settings-context';

const ExpressCheckout = () => {
	const [
		isPaymentRequestEnabled,
		updateIsPaymentRequestEnabled,
	] = usePaymentRequestEnabledSettings();

	const [
		isPlatformCheckoutEnabled,
		updateIsPlatformCheckoutEnabled,
	] = usePlatformCheckoutEnabledSettings();

	const {
		featureFlags: {
			platformCheckout: isPlatformCheckoutFeatureFlagEnabled,
		},
	} = useContext( WCPaySettingsContext );

	return (
		<Card className="express-checkouts">
			<CardBody size={ 0 }>
				<ul className="express-checkouts-list">
					{ isPlatformCheckoutFeatureFlagEnabled && (
						<li className="express-checkout has-icon-border">
							<div className="express-checkout__checkbox">
								<CheckboxControl
									checked={ isPlatformCheckoutEnabled }
									onChange={ updateIsPlatformCheckoutEnabled }
								/>
							</div>
							<div className="express-checkout__icon">
								<WooIcon />
							</div>
							<div className="express-checkout__label">
								{ __(
									'Platform Checkout',
									'woocommerce-payments'
								) }
							</div>
							<div className="express-checkout__link">
								<a
									href={ getPaymentMethodSettingsUrl(
										'platform_checkout'
									) }
								>
									{ __(
										'Customize',
										'woocommerce-payments'
									) }
								</a>
							</div>
						</li>
					) }
					<li className="express-checkout has-icon-border">
						<div className="express-checkout__checkbox">
							<CheckboxControl
								checked={ isPaymentRequestEnabled }
								onChange={ updateIsPaymentRequestEnabled }
							/>
						</div>
						<div className="express-checkout__icon">
							<PaymentRequestIcon />
						</div>
						<div className="express-checkout__label-container">
							<div className="express-checkout__label">
								{ __(
									'Apple Pay / Google Pay',
									'woocommerce-payments'
								) }
							</div>
							<div className="express-checkout__description">
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
						<div className="express-checkout__link">
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

export default ExpressCheckout;
