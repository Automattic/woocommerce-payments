/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
} from 'wcpay/components/wp-components-wrapped';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useWooPayEnabledSettings,
} from 'wcpay/data';
import './style.scss';
import { LinkIcon } from 'wcpay/payment-methods-icons';
import InlineNotice from 'wcpay/components/inline-notice';

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

	return (
		<li className="express-checkout" id="express-checkouts-link">
			<div className="express-checkout__row">
				<div className="express-checkout__checkbox">
					<CheckboxControl
						label={ __( 'Link by Stripe', 'woocommerce-payments' ) }
						disabled={ isWooPayEnabled }
						checked={ isStripeLinkEnabled }
						onChange={ updateStripeLinkCheckout }
					/>
				</div>
				<div className="express-checkout__text-container">
					<div>
						<div className="express-checkout__subgroup">
							<div className="express-checkout__icon">
								<LinkIcon />
							</div>
							<div className="express-checkout__label express-checkout__label-mobile">
								{ __(
									'Link by Stripe',
									'woocommerce-payments'
								) }
							</div>
							<div className="express-checkout__label-container">
								<div className="express-checkout__label express-checkout__label-desktop">
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
