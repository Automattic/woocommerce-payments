/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Card, CheckboxControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { useContext, useState } from '@wordpress/element';

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
import PaymentRequestIcon from '../../gateway-icons/payment-request';
import WooIcon from '../../gateway-icons/woo';
import './style.scss';
import WCPaySettingsContext from '../wcpay-settings-context';
import LinkIcon from '../../gateway-icons/link';
import ConfirmationModal from 'wcpay/components/confirmation-modal';

const LinkWoopayCompatibilityConfirmationModal = ( {
	invoker,
	onCancel,
	onConfirm,
} ) => {
	return (
		<>
			<ConfirmationModal
				className="disable-modal-section"
				title={ __(
					'Link compatibility notice',
					'woocommerce-payments'
				) }
				onRequestClose={ onCancel }
				actions={
					<>
						<Button isSecondary onClick={ onCancel }>
							{ __( 'Cancel', 'woocommerce-payments' ) }
						</Button>
						<Button isPrimary isDestructive onClick={ onConfirm }>
							{ 'link' === invoker
								? __(
										'Yes, disable WooPay',
										'woocommerce-payments'
								  )
								: __(
										'Yes, disable Link by Stripe',
										'woocommerce-payments'
								  ) }
						</Button>
					</>
				}
			>
				<p>
					{ 'link' === invoker
						? __(
								// eslint-disable-next-line max-len
								'Link and WooPay are currently incompatible. In order to enable Link, you must first disable WooPay. Would you like to proceed?',
								'woocommerce-payments'
						  )
						: __(
								// eslint-disable-next-line max-len
								'Link and WooPay are currently incompatible. In order to enable WooPay, you must first disable Link. Would you like to proceed?',
								'woocommerce-payments'
						  ) }
				</p>
			</ConfirmationModal>
		</>
	);
};

const ExpressCheckout = () => {
	const [
		compatibilityModalInvoker,
		setCompatibilityModalInvoker,
	] = useState( '' );

	const [
		isPaymentRequestEnabled,
		updateIsPaymentRequestEnabled,
	] = usePaymentRequestEnabledSettings();

	const [
		isPlatformCheckoutEnabled,
		toggleIsPlatformCheckoutEnabled,
	] = usePlatformCheckoutEnabledSettings();

	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

	const [
		enabledMethodIds,
		updateEnabledMethodIds,
	] = useEnabledPaymentMethodIds();

	const toggleStripeLinkCheckout = ( isEnabled ) => {
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

	const handleCompatibilityModalConfirmation = () => {
		toggleIsPlatformCheckoutEnabled( 'link' !== compatibilityModalInvoker );
		toggleStripeLinkCheckout( 'link' === compatibilityModalInvoker );
		setCompatibilityModalInvoker( '' );
	};

	const handleExpressCheckoutChange = ( isEnabled ) => {
		if ( isEnabled && isStripeLinkEnabled ) {
			setCompatibilityModalInvoker( 'woopay' );

			return;
		}

		toggleIsPlatformCheckoutEnabled( isEnabled );
	};

	const handleStripeLinkChange = ( isEnabled ) => {
		if ( isEnabled && isPlatformCheckoutEnabled ) {
			setCompatibilityModalInvoker( 'link' );

			return;
		}

		toggleStripeLinkCheckout( isEnabled );
	};

	return (
		<Card className="express-checkouts">
			<CardBody size={ 0 }>
				{ compatibilityModalInvoker && (
					<LinkWoopayCompatibilityConfirmationModal
						invoker={ compatibilityModalInvoker }
						onCancel={ () => setCompatibilityModalInvoker( '' ) }
						onConfirm={ handleCompatibilityModalConfirmation }
					/>
				) }
				<ul className="express-checkouts-list">
					{ isPlatformCheckoutFeatureFlagEnabled && (
						<li className="express-checkout has-icon-border">
							<div className="express-checkout__checkbox">
								<CheckboxControl
									checked={ isPlatformCheckoutEnabled }
									onChange={ handleExpressCheckoutChange }
									label={ __(
										'WooPay',
										'woocommerce-payments'
									) }
								/>
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
												'By using WooPay, you agree to the {{tosLink}}WooCommerce Terms of Service{{/tosLink}} ' +
													'and acknowledge you have read our {{privacyLink}}Privacy Policy{{/privacyLink}} and ' +
													'understand you will be sharing data with us. ' +
													'{{trackingLink}}Click here{{/trackingLink}} to learn more about data sharing. ' +
													'You can opt out of data sharing by disabling WooPay.',
												'woocommerce-payments'
											),
											components: {
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
						<li className="express-checkout has-icon-border">
							<div className="express-checkout__checkbox loadable-checkbox label-hidden">
								<CheckboxControl
									label={ __(
										'Link by Stripe',
										'woocommerce-payments'
									) }
									checked={ isStripeLinkEnabled }
									onChange={ handleStripeLinkChange }
								/>
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
