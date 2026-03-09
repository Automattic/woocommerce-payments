/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import { Button } from '@wordpress/components';
import {
	useEnabledPaymentMethodIds,
	useGetAvailablePaymentMethodIds,
	useWooPayEnabledSettings,
} from 'wcpay/data';
import InlineNotice from 'wcpay/components/inline-notice';
import methodsConfiguration from 'wcpay/payment-methods-map';
import PaymentMethodItem from 'wcpay/components/payment-method-item';

const LinkExpressCheckoutItem = (): React.ReactElement | null => {
	const availablePaymentMethodIds = useGetAvailablePaymentMethodIds();

	const [ isWooPayEnabled ] = useWooPayEnabledSettings();

	const [
		enabledMethodIds,
		updateEnabledMethodIds,
	] = useEnabledPaymentMethodIds();

	const updateStripeLinkCheckout = ( isEnabled: boolean ) => {
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
		<PaymentMethodItem
			className="express-checkout"
			id="express-checkouts-link"
		>
			<PaymentMethodItem.Checkbox
				label={ methodsConfiguration.link.label }
				disabled={ isWooPayEnabled }
				checked={ isStripeLinkEnabled }
				onChange={ updateStripeLinkCheckout }
			/>
			<PaymentMethodItem.Body>
				<div>
					<PaymentMethodItem.Subgroup
						Icon={ LinkIcon }
						label={ methodsConfiguration.link.label }
					>
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
						}
					</PaymentMethodItem.Subgroup>
				</div>
				<PaymentMethodItem.Action>
					<Button
						target="_blank"
						rel="noreferrer"
						/* eslint-disable-next-line max-len */
						href="https://woocommerce.com/document/woopayments/payment-methods/link-by-stripe/"
						isSecondary
					>
						{ __( 'Read more', 'woocommerce-payments' ) }
					</Button>
				</PaymentMethodItem.Action>
			</PaymentMethodItem.Body>
			{ isWooPayEnabled && (
				<InlineNotice status="warning" isDismissible={ false }>
					{ __(
						'To enable Link by Stripe, you must first disable WooPay.',
						'woocommerce-payments'
					) }
				</InlineNotice>
			) }
		</PaymentMethodItem>
	);
};

export default LinkExpressCheckoutItem;
