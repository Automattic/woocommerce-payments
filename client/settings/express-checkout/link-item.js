/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { CheckboxControl, VisuallyHidden } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import { Icon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useWooPayEnabledSettings,
} from 'wcpay/data';
import './style.scss';
import { HoverTooltip } from 'components/tooltip';
import LinkIcon from 'assets/images/payment-methods/link.svg?asset';
import Warning from 'components/icons/warning';

const LinkExpressCheckoutItem = () => {
	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

	const [ isWooPayEnabled ] = useWooPayEnabledSettings();

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
				...enabledMethodIds.filter( ( id ) => id !== 'link' ),
			] );
		}
	};

	const displayLinkPaymentMethod =
		enabledMethodIds.includes( 'card' ) &&
		availablePaymentMethodIds.includes( 'link' );
	const isStripeLinkEnabled = enabledMethodIds.includes( 'link' );

	return (
		<>
			{ displayLinkPaymentMethod && (
				<li className="express-checkout" id="express-checkouts-link">
					<div className="express-checkout__row">
						<div className="express-checkout__checkbox">
							{ isWooPayEnabled ? (
								<HoverTooltip
									content={ __(
										'To enable Link by Stripe, you must first disable WooPay.',
										'woocommerce-payments'
									) }
								>
									<div className="loadable-checkbox__icon">
										<Icon icon={ Warning } />
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
					</div>
				</li>
			) }
		</>
	);
};

export default LinkExpressCheckoutItem;
