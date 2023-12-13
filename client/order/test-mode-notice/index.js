/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import InlineNotice from 'wcpay/components/inline-notice';

const TestModeNotice = () => {
	return (
		<InlineNotice icon={ true } status="warning" isDismissible={ false }>
			{ __(
				'WooPayments was in test mode when this order was placed.',
				'woocommerce-payments'
			) }
			{ createInterpolateElement(
				__(
					' <a>Learn more about test mode</a>',
					'woocommerce-payments'
				),
				{
					a: (
						// createInterpolateElement is incompatible with this eslint rule as the <a> is decoupled from content.
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						<a href="https://woo.com/document/woopayments/testing-and-troubleshooting/testing/" />
					),
				}
			) }
		</InlineNotice>
	);
};

export default TestModeNotice;
