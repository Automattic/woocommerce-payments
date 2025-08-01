/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import methodsConfiguration from 'wcpay/payment-methods-map';
import { upeCapabilityStatuses } from 'wcpay/settings/constants';
import {
	useEnabledPaymentMethodIds,
	useGetPaymentMethodStatuses,
	useManualCapture,
} from 'wcpay/data';
import PAYMENT_METHOD_IDS from 'wcpay/constants/payment-method';
import { getMissingCurrenciesTooltipMessage } from 'multi-currency/utils/missing-currencies-message';
import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import { ExternalLink } from '@wordpress/components';
import { ChipType } from 'wcpay/components/chip';

const documentationTypeMap = {
	DEFAULT:
		'https://woocommerce.com/document/woopayments/payment-methods/additional-payment-methods/#method-cant-be-enabled',
	BNPLS:
		'https://woocommerce.com/document/woopayments/payment-methods/buy-now-pay-later/#contact-support',
};

const getDocumentationUrlForDisabledPaymentMethod = (
	paymentMethodId: string
): string => {
	if ( methodsConfiguration?.[ paymentMethodId ]?.allows_pay_later ) {
		return documentationTypeMap.BNPLS;
	}

	return documentationTypeMap.DEFAULT;
};

/**
 * Used to determine the UI state of a payment method, based on a few factors:
 * - whether the payment method is enabled
 * - the payment method's capability status
 *
 * The returned object contains the message that needs to be displayed to the merchant,
 * and whether the payment method is actionable (i.e. can be enabled or disabled for checkout).
 *
 * @param id
 */
const usePaymentMethodAvailability = ( id: string ) => {
	const paymentMethodStatuses = useGetPaymentMethodStatuses();
	const [ enabledPaymentMethods ] = useEnabledPaymentMethodIds();
	const [ isManualCaptureEnabled ] = useManualCapture();

	const {
		stripe_key: stripeKey,
		currencies,
		label,
		allows_manual_capture: isAllowingManualCapture,
	} = methodsConfiguration[ id ];

	const { status } = paymentMethodStatuses[ stripeKey ] ?? {
		status: upeCapabilityStatuses.UNREQUESTED,
		requirements: [],
	};

	if ( upeCapabilityStatuses.INACTIVE === status ) {
		return {
			isActionable: false,
			chip: __( 'More information needed', 'woocommerce-payments' ),
			notice: interpolateComponents( {
				// translators: {{learnMoreLink}}: placeholders are opening and closing anchor tags.
				mixedString: __(
					'We need more information from you to enable this method. ' +
						'{{learnMoreLink}}Learn more{{/learnMoreLink}}',
					'woocommerce-payments'
				),
				components: {
					learnMoreLink: (
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						<ExternalLink
							title={ __(
								'Learn more about enabling payment methods',
								'woocommerce-payments'
							) }
							href={ getDocumentationUrlForDisabledPaymentMethod(
								id
							) }
						/>
					),
				},
			} ),
		};
	}

	if ( upeCapabilityStatuses.PENDING_APPROVAL === status ) {
		const paymentMethodsWithDelayedApproval: string[] = [
			PAYMENT_METHOD_IDS.ALIPAY,
			PAYMENT_METHOD_IDS.WECHAT_PAY,
		];

		return {
			isActionable: false,
			chip: __( 'Approval pending', 'woocommerce-payments' ),
			notice: paymentMethodsWithDelayedApproval.includes( id )
				? sprintf(
						__(
							'%s requires your store to be live and fully functional before it can be reviewed for use with their service. This approval process usually takes 2-3 days.',
							'woocommerce-payments'
						),
						label
				  )
				: __(
						'This payment method is pending approval. Once approved, you will be able to use it.',
						'woocommerce-payments'
				  ),
		};
	}

	if ( upeCapabilityStatuses.PENDING_VERIFICATION === status ) {
		return {
			isActionable: false,
			chip: __( 'Pending verification', 'woocommerce-payments' ),
			notice: wcpaySettings?.accountEmail
				? sprintf(
						__(
							"%s won't be visible to your customers until you provide the required " +
								'information. Follow the instructions sent by our partner Stripe to %s.',
							'woocommerce-payments'
						),
						label,
						wcpaySettings?.accountEmail
				  )
				: sprintf(
						__(
							"%s won't be visible to your customers until you provide the required " +
								'information. Follow the instructions sent by our partner Stripe to your email.',
							'woocommerce-payments'
						),
						label
				  ),
		};
	}

	if ( upeCapabilityStatuses.REJECTED === status ) {
		return {
			isActionable: false,
			chip: __( 'Rejected', 'woocommerce-payments' ),
			chipType: 'alert' as ChipType,
			notice: interpolateComponents( {
				// translators: {{learnMoreLink}}: placeholders are opening and closing anchor tags.
				mixedString: sprintf(
					__(
						'Your application to use %s has been rejected, please check your email for more information. Need help? {{contactSupportLink}}Contact support{{/contactSupportLink}}',
						'woocommerce-payments'
					),
					label
				),
				components: {
					contactSupportLink: (
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						<ExternalLink
							title={ __(
								'Contact Support',
								'woocommerce-payments'
							) }
							href={
								'https://woocommerce.com/my-account/contact-support/'
							}
						/>
					),
				},
			} ),
			noticeType: 'error' as const,
		};
	}

	if ( isManualCaptureEnabled && ! isAllowingManualCapture ) {
		return {
			isActionable: false,
			notice: sprintf(
				/* translators: %s: a payment method name. */
				__(
					'%s is not available to your customers when the "manual capture" setting is enabled.',
					'woocommerce-payments'
				),
				label
			),
		};
	}

	if (
		! wcpaySettings.isMultiCurrencyEnabled &&
		id !== PAYMENT_METHOD_IDS.CARD &&
		enabledPaymentMethods.includes( id )
	) {
		const currency = wcpaySettings.storeCurrency;
		if ( currencies.indexOf( currency ) < 0 ) {
			return {
				isActionable: true,
				notice: getMissingCurrenciesTooltipMessage( label, currencies ),
			};
		}
	}

	return {
		isActionable: true,
	};
};

export default usePaymentMethodAvailability;
