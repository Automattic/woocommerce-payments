/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import { getPaymentMethodSettingsUrl } from '../../utils';
import { useContext } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Button } from '@wordpress/components';
import {
	useEnabledPaymentMethodIds,
	useWooPayEnabledSettings,
	useWooPayShowIncompatibilityNotice,
} from 'wcpay/data';
import WCPaySettingsContext from '../wcpay-settings-context';
import { WooPayIncompatibilityNotice } from '../settings-warnings/incompatibility-notice';
import { WooIcon } from 'wcpay/payment-methods-icons';
import InlineNotice from 'wcpay/components/inline-notice';
import PaymentMethodItem from 'wcpay/components/payment-method-item';

const WooPayExpressCheckoutItem = (): React.ReactElement | null => {
	const [ enabledMethodIds ] = useEnabledPaymentMethodIds();

	const [
		isWooPayEnabled,
		updateIsWooPayEnabled,
	] = useWooPayEnabledSettings();

	const isStripeLinkEnabled = enabledMethodIds.includes( 'link' );

	const showIncompatibilityNotice =
		useWooPayShowIncompatibilityNotice() && ! isStripeLinkEnabled;

	const {
		featureFlags: { woopay: isWooPayFeatureFlagEnabled },
	} = useContext( WCPaySettingsContext );

	if ( ! isWooPayFeatureFlagEnabled ) {
		return null;
	}

	return (
		<PaymentMethodItem
			className="express-checkout"
			id="express-checkouts-woopay"
		>
			<PaymentMethodItem.Checkbox
				label={ __( 'WooPay', 'woocommerce-payments' ) }
				checked={ isWooPayEnabled }
				disabled={ isStripeLinkEnabled }
				onChange={ updateIsWooPayEnabled }
				data-testid="woopay-toggle"
			/>
			<PaymentMethodItem.Body>
				<div>
					<PaymentMethodItem.Subgroup
						Icon={ WooIcon }
						label={ __( 'WooPay', 'woocommerce-payments' ) }
					>
						{
							/* eslint-disable jsx-a11y/anchor-has-content */
							isWooPayEnabled
								? __(
										'Boost conversion and customer loyalty by' +
											' offering a single click, secure way to pay.',
										'woocommerce-payments'
								  )
								: interpolateComponents( {
										mixedString: __(
											/* eslint-disable-next-line max-len */
											'Boost conversion and customer loyalty by offering a single click, secure way to pay. ' +
												'In order to use {{wooPayLink}}WooPay{{/wooPayLink}},' +
												' you must agree to our ' +
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
													// eslint-disable-next-line max-len
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
					</PaymentMethodItem.Subgroup>
				</div>
				<PaymentMethodItem.Action>
					<Button
						href={ getPaymentMethodSettingsUrl( 'woopay' ) }
						isSecondary
					>
						{ __( 'Customize', 'woocommerce-payments' ) }
					</Button>
				</PaymentMethodItem.Action>
			</PaymentMethodItem.Body>
			{ isStripeLinkEnabled && (
				<InlineNotice status="warning" isDismissible={ false }>
					{ __(
						'To enable WooPay, you must first disable Link by Stripe.',
						'woocommerce-payments'
					) }
				</InlineNotice>
			) }
			{ showIncompatibilityNotice && <WooPayIncompatibilityNotice /> }
		</PaymentMethodItem>
	);
};

export default WooPayExpressCheckoutItem;
