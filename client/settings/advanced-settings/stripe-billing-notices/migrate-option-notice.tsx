/**
 * External dependencies
 */
import React, { useContext } from 'react';
import InlineNotice from 'wcpay/components/inline-notice';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import StripeBillingMigrationNoticeContext from './context';

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

	/**
	 * Whether the request to start a migration has finished.
	 */
	hasResolved: boolean;
}

const MigrateOptionNotice: React.FC< Props > = ( {
	stripeBillingSubscriptionCount,
	startMigration,
	isLoading,
	hasResolved,
} ) => {
	const context = useContext( StripeBillingMigrationNoticeContext );
	const { setIsMigrationInProgress } = context;

	// The class name of the action which sends the request to migrate.
	const noticeClassName = 'woopayments-migrate-stripe-billing-action';

	// Add the `is-busy` class to the button while we process the migrate request.
	useEffect( () => {
		const button = document.querySelector(
			`.${ noticeClassName } .wcpay-inline-notice__action`
		);

		if ( button ) {
			if ( isLoading ) {
				button.classList.add( 'is-busy' );
			} else {
				button.classList.remove( 'is-busy' );
			}
		}
	}, [ isLoading ] );

	// Once the request is resolved, mark the migration as in progress. The parent
	// derives `isMigrationOptionShown` from `hasResolved`, so no explicit hide is needed.
	useEffect( () => {
		if ( hasResolved ) {
			setIsMigrationInProgress( true );
		}
	}, [ hasResolved, setIsMigrationInProgress ] );

	if ( ! context.isMigrationOptionShown ) {
		return null;
	}

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
						// @ts-expect-error: children is provided when interpolating the component
						<ExternalLink href="https://woocommerce.com/document/woopayments/subscriptions/stripe-billing/#migrating-subscribers" />
					),
				},
			} ) }
		</InlineNotice>
	);
};

export default MigrateOptionNotice;
