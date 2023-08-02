/** @format */
/**
 * External dependencies
 */
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';
import React from 'react';

/**
 * Internal dependencies
 */
import { HoverTooltip } from 'components/tooltip';
import PAYMENT_METHOD_IDS from 'wcpay/payment-methods/constants';

export const DocumentationUrlForDisabledPaymentMethod = {
	DEFAULT:
		'https://woocommerce.com/document/woopayments/payment-methods/additional-payment-methods/#method-cant-be-enabled',
	BNPLS:
		'https://woocommerce.com/document/woopayments/payment-methods/buy-now-pay-later/#contact-support',
};

export const getDocumentationUrlForDisabledPaymentMethod = (
	paymentMethodId: string
): string => {
	let url;
	switch ( paymentMethodId ) {
		case PAYMENT_METHOD_IDS.AFTERPAY_CLEARPAY:
		case PAYMENT_METHOD_IDS.AFFIRM:
			url = DocumentationUrlForDisabledPaymentMethod.BNPLS;
			break;
		default:
			url = DocumentationUrlForDisabledPaymentMethod.DEFAULT;
	}
	return url;
};

const PaymentMethodDisabledTooltip = ( {
	id,
	children,
}: {
	id: string;
	children: React.ReactNode;
} ): React.ReactElement => {
	return (
		<HoverTooltip
			content={ interpolateComponents( {
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
							/* eslint-disable-next-line max-len */
							href={ getDocumentationUrlForDisabledPaymentMethod(
								id
							) }
						/>
					),
				},
			} ) }
		>
			{ children }
		</HoverTooltip>
	);
};

export default PaymentMethodDisabledTooltip;
