/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import { Button, CheckboxControl } from '@wordpress/components';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useWooPayEnabledSettings,
} from 'wcpay/data';
import './style.scss';
import InlineNotice from 'wcpay/components/inline-notice';
import methodsConfiguration from 'wcpay/payment-methods-map';

const LinkExpressCheckoutItem = (): React.ReactElement | null => {
	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

	const [ isWooPayEnabled ] = useWooPayEnabledSettings();

	const [
		enabledMethodIds,
		updateEnabledMethodIds,
	] = useEnabledPaymentMethodIds();

	const updateStripeLinkCheckout = ( isEnabled: boolean ) => {
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

	if ( ! displayLinkPaymentMethod ) {
		return null;
	}

	const { icon: LinkIcon } = methodsConfiguration.link;

	return (
		<li className="express-checkout" id="express-checkouts-link">
			<div className="express-checkout__row">
				<div className="express-checkout__checkbox">
					<CheckboxControl
						label={ methodsConfiguration.link.label }
						disabled={ isWooPayEnabled }
						checked={ isStripeLinkEnabled }
						onChange={ updateStripeLinkCheckout }
						__nextHasNoMarginBottom
					/>
				</div>
				<div className="express-checkout__text-container">
					<div>
						<div className="express-checkout__subgroup">
							<div className="express-checkout__icon">
								<LinkIcon />
							</div>
							<div className="express-checkout__label express-checkout__label-mobile">
								{ methodsConfiguration.link.label }
							</div>
							<div className="express-checkout__label-container">
								<div className="express-checkout__label express-checkout__label-desktop">
									{ methodsConfiguration.link.label }
								</div>
								<div className="express-checkout__description">
									{ methodsConfiguration.link.description }
									{ ! isStripeLinkEnabled && ' ' }
									{
										/* eslint-disable jsx-a11y/anchor-has-content */
										! isStripeLinkEnabled &&
											interpolateComponents( {
												mixedString: __(
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
															href="https://link.com/terms"
														/>
													),
													privacyPolicy: (
														<a
															target="_blank"
															rel="noreferrer"
															href="https://link.com/privacy"
														/>
													),
												},
											} )
										/* eslint-enable jsx-a11y/anchor-has-content */
										/* eslint-enable max-len */
									}
								</div>
							</div>
						</div>
					</div>
					<div className="express-checkout__link">
						<Button
							target="_blank"
							rel="noreferrer"
							/* eslint-disable-next-line max-len */
							href="https://woocommerce.com/document/woopayments/payment-methods/link-by-stripe/"
							isSecondary
						>
							{ __( 'Read more', 'woocommerce-payments' ) }
						</Button>
					</div>
				</div>
			</div>
			{ isWooPayEnabled && (
				<InlineNotice status="warning" isDismissible={ false }>
					{ __(
						'To enable Link by Stripe, you must first disable WooPay.',
						'woocommerce-payments'
					) }
				</InlineNotice>
			) }
		</li>
	);
};

export default LinkExpressCheckoutItem;
