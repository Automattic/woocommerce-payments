/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { CheckboxControl, ExternalLink } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import StripeBillingMigrationNoticeContext from './stripe-billing-notices/context';

interface Props {
	/**
	 * The function to run when the checkbox is changed.
	 */
	onChange: ( enabled: boolean ) => void;
}

/**
 * Renders a WooPayments subscriptions settings card.
 *
 * @return {JSX.Element} Rendered subscriptions element.
 */
const StripeBillingToggle: React.FC< Props > = ( { onChange } ) => {
	const context = useContext( StripeBillingMigrationNoticeContext );

	return (
		<CheckboxControl
			checked={ context.isStripeBillingEnabled }
			onChange={ onChange }
			label={ __(
				'Enable Stripe Billing for future subscriptions',
				'woocommerce-payments'
			) }
			help={ interpolateComponents( {
				mixedString: sprintf(
					context.isMigrationOptionShown
						? __(
								'Alternatively, you can enable this setting and future %s subscription purchases will also utilize' +
									' Stripe Billing for payment processing. Note: This feature supports card payments only and' +
									' may lack support for key subscription features.' +
									' {{learnMoreLink}}Learn more{{/learnMoreLink}}',
								'woocommerce-payments'
						  )
						: __(
								'By enabling this setting, future %s subscription purchases will utilize Stripe Billing for payment' +
									' processing. Note: This feature supports card payments only and may lack support for key' +
									' subscription features. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
								'woocommerce-payments'
						  ),
					'WooPayments'
				),
				components: {
					learnMoreLink: (
						// eslint-disable-next-line max-len
						<ExternalLink href="https://woocommerce.com/document/woopayments/built-in-subscriptions/comparison/#billing-engine" />
					),
				},
			} ) }
		/>
	);
};

export default StripeBillingToggle;
