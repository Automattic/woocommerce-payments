/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { getPaymentMethodSettingsUrl } from '../../utils';

/**
 * Internal dependencies
 */
import { Button } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import methodsConfiguration from '../../payment-methods-map';
import { useAmazonPayEnabledSettings } from 'wcpay/data';
import usePaymentMethodAvailability from 'wcpay/settings/payment-methods-list/use-payment-method-availability';
import InlineNotice from 'wcpay/components/inline-notice';
import PaymentMethodItem from 'wcpay/components/payment-method-item';

const AmazonPayExpressCheckoutItem = (): React.ReactElement => {
	const [
		isAmazonPayEnabled,
		updateIsAmazonPayEnabled,
	] = useAmazonPayEnabledSettings();

	const {
		icon: AmazonPayIcon,
		label,
		description,
	} = methodsConfiguration.amazon_pay;

	const {
		isActionable,
		notice,
		noticeType = 'warning' as const,
	} = usePaymentMethodAvailability( 'amazon_pay' );

	return (
		<PaymentMethodItem
			className="express-checkout"
			id="express-checkouts-amazon-pay"
		>
			<PaymentMethodItem.Checkbox
				label={ label }
				checked={ isAmazonPayEnabled }
				disabled={ ! isActionable }
				onChange={ updateIsAmazonPayEnabled }
			/>
			<PaymentMethodItem.Body>
				<div>
					<PaymentMethodItem.Subgroup
						Icon={ AmazonPayIcon }
						label={ label }
					>
						{ description + ' ' }
						{ interpolateComponents( {
							mixedString: __(
								/* eslint-disable-next-line max-len */
								'By activating this feature, you accept ' +
									'{{stripeLink}}Stripe{{/stripeLink}} and ' +
									"{{amazonLink}}Amazon{{/amazonLink}}'s terms of use.",
								'woocommerce-payments'
							),
							/* eslint-disable jsx-a11y/anchor-has-content */
							components: {
								stripeLink: (
									<a
										target="_blank"
										rel="noreferrer"
										href="https://stripe.com/legal/ssa"
									/>
								),
								amazonLink: (
									<a
										target="_blank"
										rel="noreferrer"
										href="https://stripe.com/legal/amazon-pay"
									/>
								),
							},
							/* eslint-enable jsx-a11y/anchor-has-content */
						} ) }
					</PaymentMethodItem.Subgroup>
				</div>
				<PaymentMethodItem.Action>
					<Button
						href={ getPaymentMethodSettingsUrl( 'amazon_pay' ) }
						isSecondary
					>
						{ __( 'Customize', 'woocommerce-payments' ) }
					</Button>
				</PaymentMethodItem.Action>
			</PaymentMethodItem.Body>
			{ notice && (
				<InlineNotice status={ noticeType } isDismissible={ false }>
					{ notice }
				</InlineNotice>
			) }
		</PaymentMethodItem>
	);
};

export default AmazonPayExpressCheckoutItem;
