/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl, VisuallyHidden } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { useContext } from '@wordpress/element';
import { Icon, warning } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { getPaymentMethodSettingsUrl } from '../../utils';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	usePaymentRequestEnabledSettings,
	usePlatformCheckoutEnabledSettings,
} from 'wcpay/data';
import CardBody from '../card-body';
import WooIcon from '../../gateway-icons/woo';
import './style.scss';
import WCPaySettingsContext from '../wcpay-settings-context';
import LinkIcon from '../../gateway-icons/link';
import Tooltip from 'components/tooltip';
import ApplePay from 'wcpay/gateway-icons/apple-pay';
import GooglePay from 'wcpay/gateway-icons/google-pay';

const ExpressCheckout = () => {
	const [
		isPaymentRequestEnabled,
		updateIsPaymentRequestEnabled,
	] = usePaymentRequestEnabledSettings();

	const [
		isPlatformCheckoutEnabled,
		updateIsPlatformCheckoutEnabled,
	] = usePlatformCheckoutEnabledSettings();

	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

	const [
		enabledMethodIds,
		updateEnabledMethodIds,
	] = useEnabledPaymentMethodIds();

	const updateStripeLinkCheckout = ( isEnabled ) => {
		//this handles the link payment method checkbox. If it's enable we should add link to the rest of the
		//enabled payment method.
		// If false - we should remove link payment method from the enabled payment methods
		if ( isEnabled ) {
			updateEnabledMethodIds( [
				...new Set( [ ...enabledMethodIds, 'link' ] ),
			] );
		} else {
			updateEnabledMethodIds( [
				...enabledMethodIds.filter( ( id ) => 'link' !== id ),
			] );
		}
	};

	const displayLinkPaymentMethod =
		enabledMethodIds.includes( 'card' ) &&
		availablePaymentMethodIds.includes( 'link' );
	const isStripeLinkEnabled = enabledMethodIds.includes( 'link' );

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
						<li className="express-checkout">
							<div className="express-checkout__checkbox">
								{ isStripeLinkEnabled ? (
									<Tooltip
										content={ __(
											'To enable WooPay, you must first disable Link by Stripe.',
											'woocommerce-payments'
										) }
									>
										<div className="loadable-checkbox__icon">
											<Icon
												icon={ warning }
												fill={ '#ffc83f' }
											/>
											<div
												className="loadable-checkbox__icon-warning"
												data-testid="loadable-checkbox-icon-warning"
											>
												<VisuallyHidden>
													{ __(
														'WooPay cannot be enabled at checkout. Click to expand.',
														'woocommerce-payments'
													) }
												</VisuallyHidden>
											</div>
										</div>
									</Tooltip>
								) : (
									<CheckboxControl
										label={ __(
											'WooPay',
											'woocommerce-payments'
										) }
										checked={ isPlatformCheckoutEnabled }
										onChange={
											updateIsPlatformCheckoutEnabled
										}
									/>
								) }
							</div>
							<div className="express-checkout__icon">
								<WooIcon />
							</div>
							<div className="express-checkout__label-container">
								<div className="express-checkout__label">
									{ __( 'WooPay', 'woocommerce-payments' ) }
								</div>
								<div className="express-checkout__description">
									{
										/* eslint-disable jsx-a11y/anchor-has-content */
										interpolateComponents( {
											mixedString: __(
												'Boost conversion and customer loyalty by offering a single click, secure way to pay. ' +
													'By using {{wooPayLink}}WooPay{{/wooPayLink}}, you agree to our ' +
													'{{tosLink}}WooCommerce Terms of Service{{/tosLink}} ' +
													'and and {{privacyLink}}Privacy Policy{{/privacyLink}}. ' +
													'You understand you will be sharing data with us. ' +
													'{{trackingLink}}Click here{{/trackingLink}} to learn more about the ' +
													'data you will be sharing and opt-out options.',
												'woocommerce-payments'
											),
											components: {
												wooPayLink: (
													<a
														target="_blank"
														rel="noreferrer"
														href="https://woocommerce.com/document/woopay-merchant-documentation/"
													/>
												),
												tosLink: (
													<a
														target="_blank"
														rel="noreferrer"
														href="https://wordpress.com/tos/"
													/>
												),
												privacyLink: (
													<a
														target="_blank"
														rel="noreferrer"
														href="https://automattic.com/privacy/"
													/>
												),
												trackingLink: (
													<a
														target="_blank"
														rel="noreferrer"
														href="https://woocommerce.com/usage-tracking/"
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
					<li className="express-checkout">
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
						<div className="express-checkout__icons">
							<div className="express-checkout__icon">
								<ApplePay />
							</div>
							<div className="express-checkout__icon">
								<GooglePay />
							</div>
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
											'Boost sales by offering a fast, simple, and secure checkout experience.' +
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
					{ displayLinkPaymentMethod && (
						<li className="express-checkout">
							<div className="express-checkout__checkbox">
								{ isPlatformCheckoutEnabled ? (
									<Tooltip
										content={ __(
											'To enable Link by Stripe, you must first disable WooPay.',
											'woocommerce-payments'
										) }
									>
										<div className="loadable-checkbox__icon">
											<Icon
												icon={ warning }
												fill={ '#ffc83f' }
											/>
											<div
												className="loadable-checkbox__icon-warning"
												data-testid="loadable-checkbox-icon-warning"
											>
												<VisuallyHidden>
													{ __(
														'Link by Stripe cannot be enabled at checkout. Click to expand.',
														'woocommerce-payments'
													) }
												</VisuallyHidden>
											</div>
										</div>
									</Tooltip>
								) : (
									<CheckboxControl
										label={ __(
											'Link by Stripe',
											'woocommerce-payments'
										) }
										checked={ isStripeLinkEnabled }
										onChange={ updateStripeLinkCheckout }
									/>
								) }
							</div>
							<div className="express-checkout__icon">
								<LinkIcon />
							</div>
							<div className="express-checkout__label-container">
								<div className="express-checkout__label">
									{ __(
										'Link by Stripe',
										'woocommerce-payments'
									) }
								</div>
								<div className="express-checkout__description">
									{
										/* eslint-disable jsx-a11y/anchor-has-content */
										interpolateComponents( {
											mixedString: __(
												'Link autofills your customers’ payment and shipping details to ' +
													'deliver an easy and seamless checkout experience. ' +
													'New payment experience (UPE) needs to be enabled for Link. ' +
													'By enabling this feature, you agree to the ' +
													'{{stripeLinkTerms}}Link by Stripe terms{{/stripeLinkTerms}}, ' +
													'and {{privacyPolicy}}Privacy Policy{{/privacyPolicy}}.',
												'woocommerce-payments'
											),
											components: {
												stripeLinkTerms: (
													<a
														target="_blank"
														rel="noreferrer"
														href="https://link.co/terms"
													/>
												),
												privacyPolicy: (
													<a
														target="_blank"
														rel="noreferrer"
														href="https://link.co/privacy"
													/>
												),
											},
										} )
										/* eslint-enable jsx-a11y/anchor-has-content */
									}
								</div>
							</div>
							<div className="express-checkout__link">
								{
									/* eslint-disable jsx-a11y/anchor-has-content */
									interpolateComponents( {
										mixedString: __(
											'{{linkDocs}}Read more{{/linkDocs}}',
											'woocommerce-payments'
										),
										components: {
											linkDocs: (
												<a
													target="_blank"
													rel="noreferrer"
													/* eslint-disable-next-line max-len */
													href="https://woocommerce.com/document/payments/woocommerce-payments-stripe-link/"
												/>
											),
										},
									} )
									/* eslint-enable jsx-a11y/anchor-has-content */
								}
							</div>
						</li>
					) }
				</ul>
			</CardBody>
		</Card>
	);
};

export default ExpressCheckout;
