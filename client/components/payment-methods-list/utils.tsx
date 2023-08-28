/**
 * External dependencies
 */
import interpolateComponents from '@automattic/interpolate-components';
import { __, sprintf } from '@wordpress/i18n';
import React from 'react';

/**
 * Internal dependencies
 */
import { upeCapabilityStatuses } from 'wcpay/additional-methods-setup/constants';
import PAYMENT_METHOD_IDS from 'wcpay/payment-methods/constants';

export const getDocumentationUrlForDisabledPaymentMethod = (
	paymentMethodId: string
): string => {
	const DocumentationUrlForDisabledPaymentMethod = {
		DEFAULT:
			'https://woocommerce.com/document/woopayments/payment-methods/additional-payment-methods/#method-cant-be-enabled',
		BNPL:
			'https://woocommerce.com/document/woopayments/payment-methods/buy-now-pay-later/#contact-support',
	};
	let url;
	switch ( paymentMethodId ) {
		case PAYMENT_METHOD_IDS.AFTERPAY_CLEARPAY:
		case PAYMENT_METHOD_IDS.AFFIRM:
			url = DocumentationUrlForDisabledPaymentMethod.BNPL;
			break;
		default:
			url = DocumentationUrlForDisabledPaymentMethod.DEFAULT;
	}
	return url;
};

export const getDisabledTooltipContent = (
	isSetupRequired: boolean,
	setupTooltip: string,
	isManualCaptureEnabled: boolean,
	isAllowingManualCapture: boolean,
	status: string,
	label: string,
	paymentMethodId: string,
	accountEmail?: string
): string | JSX.Element => {
	if ( isSetupRequired ) {
		return setupTooltip;
	}

	if ( isManualCaptureEnabled && ! isAllowingManualCapture ) {
		return sprintf(
			__(
				'%s is not available to your customers when the "manual capture" setting is enabled.',
				'woocommerce-payments'
			),
			label
		);
	}

	if ( upeCapabilityStatuses.PENDING_APPROVAL === status ) {
		return __(
			'This payment method is pending approval. Once approved, you will be able to use it.',
			'woocommerce-payments'
		);
	}

	if ( upeCapabilityStatuses.PENDING_VERIFICATION === status ) {
		return sprintf(
			__(
				"%s won't be visible to your customers until you provide the required " +
					'information. Follow the instructions sent by our partner Stripe to %s.',
				'woocommerce-payments'
			),
			label,
			accountEmail ?? ''
		);
	}

	if ( status === upeCapabilityStatuses.INACTIVE ) {
		return interpolateComponents( {
			// translators: {{learnMoreLink}}: placeholders are opening and closing anchor tags.
			mixedString: __(
				'We need more information from you to enable this method. ' +
					'{{learnMoreLink}}Learn more.{{/learnMoreLink}}',
				'woocommerce-payments'
			),
			components: {
				learnMoreLink: (
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						target="_blank"
						rel="noreferrer"
						title={ __(
							'Learn more about enabling payment methods',
							'woocommerce-payments'
						) }
						href={ getDocumentationUrlForDisabledPaymentMethod(
							paymentMethodId
						) }
					/>
				),
			},
		} );
	}

	return __( 'Disabled', 'woocommerce-payments' );
};

// Utility function to calculate the chip message based on status of the payment method.
export const getChipMessage = ( status: string ): string => {
	switch ( status ) {
		case upeCapabilityStatuses.PENDING_APPROVAL:
			return __( 'Pending approval', 'woocommerce-payments' );
		case upeCapabilityStatuses.PENDING_VERIFICATION:
			return __( 'Pending activation', 'woocommerce-payments' );
		case upeCapabilityStatuses.INACTIVE:
			return __( 'More information needed', 'woocommerce-payments' );
		default:
			return '';
	}
};
