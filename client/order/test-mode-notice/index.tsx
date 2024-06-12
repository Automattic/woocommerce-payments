/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import InlineNotice from 'wcpay/components/inline-notice';

const TestModeNotice = (): JSX.Element => {
	return (
		<InlineNotice icon={ true } status="warning" isDismissible={ false }>
			{ interpolateComponents( {
				mixedString: __(
					'WooPayments was in test mode when this order was placed. {{learnMoreLink/}}',
					'woocommerce-payments'
				),
				components: {
					learnMoreLink: (
						<a
							target="_blank"
							href="https://woocommerce.com/document/woopayments/testing-and-troubleshooting/testing/"
							rel="noopener noreferrer"
						>
							{ __(
								'Learn more about test mode',
								'woocommerce-payments'
							) }
						</a>
					),
				},
			} ) }
		</InlineNotice>
	);
};

export default TestModeNotice;
