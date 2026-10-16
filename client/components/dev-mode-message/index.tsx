/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
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
			<strong>
				{ __(
					'Your store is in development mode, so payments are simulated.',
					'woocommerce-payments'
				) }
			</strong>{ ' ' }
			{ reason ? `${ reason } ` : null }
			{ interpolateComponents( {
				mixedString: isOnboarding
					? __(
							'Live accounts can only be set up on your live store. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
							'woocommerce-payments'
						)
					: __(
							'Real payments only work on your live store. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
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
