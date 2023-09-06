/** @format */
/**
 * External dependencies
 */
import React, { useContext, useState } from 'react';
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

	/**
	 * Whether the notice is eligible to be shown.
	 *
	 * Note: We use `useState` here to snapshot the setting value on load.
	 * The option notice should only be shown if Stripe Billing is disabled on load and there are subscriptions to migrate.
	 */
	const [ isEligible, setIsEligible ] = useState(
		! context.isStripeBillingEnabled
	);

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

	// The notice is no longer eligible if the settings have been saved and Stripe billing is enabled.
	useEffect( () => {
		if ( context.savedIsStripeBillingEnabled ) {
			setIsEligible( false );
		}
	}, [ context.savedIsStripeBillingEnabled ] );

	// Once the request is resolved, hide the notice and mark the migration as in progress.
	if ( hasResolved ) {
		context.isMigrationInProgress = true;
		context.isMigrationOptionShown = false;
		return null;
	}

	if ( context.isMigrationInProgress ) {
		return null;
	}

	if ( stripeBillingSubscriptionCount === 0 ) {
		return null;
	}

	if ( ! isEligible ) {
		return null;
	}

	if ( context.isStripeBillingEnabled ) {
		return null;
	}

	context.isMigrationOptionShown = true;

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
