/** @format */
/**
 * External dependencies
 */
import React from 'react';
import InlineNotice from 'wcpay/components/inline-notice';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import { useEffect } from '@wordpress/element';

interface Props {
	/**
	 * The number of subscriptions that will be migrated if a migration is started.
	 */
	stripeBillingSubscriptionCount: number;

	/**
	 * The function to call to start a migration.
	 */
	startMigration: () => void;

	/**
	 * Whether the request to start a migration is loading.
	 */
	isLoading: boolean;
}

const MigrateOptionNotice: React.FC< Props > = ( {
	stripeBillingSubscriptionCount,
	startMigration,
	isLoading,
} ) => {
	// The class name of the action which sends the request to migrate.
	const noticeClassName = 'woopayments-migrate-stripe-billing-action';

	// Add the `is-busy` class to the button while we process the migrate request.
	useEffect( () => {
		const button = document.querySelector(
			`.${ noticeClassName } .wcpay-banner-notice__action`
		);

		if ( button ) {
			if ( isLoading ) {
				button.classList.add( 'is-busy' );
			} else {
				button.classList.remove( 'is-busy' );
			}
		}
	}, [ isLoading ] );

	return (
		<InlineNotice
			status="warning"
			isDismissible={ false }
			className={ `woopayments-stripe-billing-notice ${ noticeClassName }` }
			actions={ [
				{
					label: __( 'Begin migration', 'woocommerce-payments' ),
					onClick: startMigration,
				},
			] }
		>
			{ interpolateComponents( {
				mixedString: sprintf(
					_n(
						'There is %d customer subscription using Stripe Billing for subscription renewals.' +
							' We suggest migrating it to on-site billing powered by the %s plugin.' +
							' {{learnMoreLink}}Learn more{{/learnMoreLink}}',
						'There are %d customer subscriptions using Stripe Billing for payment processing.' +
							' We suggest migrating them to on-site billing powered by the %s plugin.' +
							' {{learnMoreLink}}Learn more{{/learnMoreLink}}',
						stripeBillingSubscriptionCount,
						'woocommerce-payments'
					),
					stripeBillingSubscriptionCount,
					'Woo Subscriptions'
				),
				components: {
					learnMoreLink: (
						// eslint-disable-next-line max-len
						<ExternalLink href="https://woocommerce.com/document/woopayments/built-in-subscriptions/comparison/#billing-engine" />
					),
				},
			} ) }
		</InlineNotice>
	);
};

export default MigrateOptionNotice;
