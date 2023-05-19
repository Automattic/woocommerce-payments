/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl, VisuallyHidden } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
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
	useWooPayEnabledSettings,
} from 'wcpay/data';
import CardBody from '../card-body';
import './style.scss';
import WCPaySettingsContext from '../wcpay-settings-context';
import { HoverTooltip } from 'components/tooltip';
import ApplePay from 'assets/images/cards/apple-pay.svg?asset';
import GooglePay from 'assets/images/cards/google-pay.svg?asset';
import LinkIcon from 'assets/images/payment-methods/link.svg?asset';
import WooIcon from 'assets/images/payment-methods/woo.svg?asset';

const ExpressCheckout = () => {
	const [
		isPaymentRequestEnabled,
		updateIsPaymentRequestEnabled,
	] = usePaymentRequestEnabledSettings();

	const [
		isWooPayEnabled,
		updateIsWooPayEnabled,
	] = useWooPayEnabledSettings();

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
		featureFlags: { woopay: isWooPayFeatureFlagEnabled },
	} = useContext( WCPaySettingsContext );

	return (
		<Card className="express-checkouts">
			<CardBody size={ 0 }>
				<ul className="express-checkouts-list">
					{ isWooPayFeatureFlagEnabled && (
						<li
							className="express-checkout"
							id="express-checkouts-woopay"
						>
							<div className="express-checkout__checkbox">
								{ isStripeLinkEnabled ? (
									<HoverTooltip
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
									</HoverTooltip>
								) : (
									<CheckboxControl
										label={ __(
											'WooPay',
											'woocommerce-payments'
										) }
										checked={ isWooPayEnabled }
										onChange={ updateIsWooPayEnabled }
									/>
								) }
							</div>
							<div className="express-checkout__icon">
								<img src={ WooIcon } alt="WooPay" />
							</div>
							<div className="express-checkout__label-container">
								<div className="express-checkout__label">
									{ __( 'WooPay', 'woocommerce-payments' ) }
								</div>
								<div className="express-checkout__description">
									{
										/* eslint-disable jsx-a11y/anchor-has-content */
										isWooPayEnabled
											? __(
													'Boost conversion and customer loyalty by offering a single click, secure way to pay.',
													'woocommerce-payments'
											  )
											: interpolateComponents( {
													mixedString: __(
														/* eslint-disable-next-line max-len */
														'Boost conversion and customer loyalty by offering a single click, secure way to pay. ' +
															'In order to use {{wooPayLink}}WooPay{{/wooPayLink}}, you must agree to our ' +
															'{{tosLink}}WooCommerce Terms of Service{{/tosLink}} ' +
															'and {{privacyLink}}Privacy Policy{{/privacyLink}}. ' +
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
										'woopay'
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
					<li
						className="express-checkout"
						id="express-checkouts-apple-google-pay"
					>
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
										{ __(
											'Apple Pay',
											'woocommerce-payments'
										) }
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
										{ __(
											'Google Pay',
											'woocommerce-payments'
										) }
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
					</li>
					{ displayLinkPaymentMethod && (
						<li
							className="express-checkout"
							id="express-checkouts-link"
						>
							<div className="express-checkout__checkbox">
								{ isWooPayEnabled ? (
									<HoverTooltip
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
									</HoverTooltip>
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
								<img src={ LinkIcon } alt="Link" />
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
										isStripeLinkEnabled
											? /* eslint-disable max-len */
											  __(
													'Link autofills your customers’ payment and shipping details to deliver an easy and seamless checkout experience.',
													'woocommerce-payments'
											  )
											: interpolateComponents( {
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
										/* eslint-enable max-len */
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
