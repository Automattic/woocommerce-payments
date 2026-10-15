/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

interface Props {
	/** Whether the merchant is setting up an account rather than taking payments. */
	isOnboarding?: boolean;
	onLearnMoreClick?: () => void;
}

/**
 * The development mode message shared by every WooPayments notice shown while development mode is on.
 *
 * Keep it in sync with `WC_Payments_Admin_Settings::print_dev_mode_notice_text()`, which renders it on Settings.
 */
const DevModeMessage: React.FC< Props > = ( {
	isOnboarding = false,
	onLearnMoreClick,
} ) => {
	const reason = wcpaySettings?.devModeReason;

	return (
		<>
			{ interpolateComponents( {
				mixedString: sprintf(
					/* translators: %1$s: WooPayments */
					__(
						'{{strong}}Your store is in development mode.{{/strong}} %1$s only works in test mode here, so all payments are simulated.',
						'woocommerce-payments'
					),
					'WooPayments'
				),
				components: {
					strong: <strong />,
				},
			} ) }{ ' ' }
			{ reason ? `${ reason } ` : null }
			{ interpolateComponents( {
				mixedString: isOnboarding
					? __(
							'To set up a live account, use your live store. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
							'woocommerce-payments'
					  )
					: __(
							'To accept real payments, use your live store. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
							'woocommerce-payments'
					  ),
				components: {
					learnMoreLink: (
						// @ts-expect-error: children is provided when interpolating the component
						<ExternalLink
							href={
								'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/#developer-notes'
							}
							onClick={ onLearnMoreClick }
						/>
					),
				},
			} ) }
		</>
	);
};

export default DevModeMessage;
