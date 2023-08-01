/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import React from 'react';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import { HoverTooltip } from 'components/tooltip';
import { getDocumentationUrlForDisabledPaymentMethod } from './utils';

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
