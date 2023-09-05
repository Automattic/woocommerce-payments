/** @format */
/**
 * External dependencies
 */
import React, { useState, useContext } from 'react';
import InlineNotice from 'wcpay/components/inline-notice';
import { _n, sprintf } from '@wordpress/i18n';

import Loadable from 'wcpay/components/loadable';

/**
 * Internal dependencies
 */
import StripeBillingMigrationNoticeContext from './context';

interface Props {
	/**
	 * The number of subscriptions that are being migrated.
	 */
	stripeBillingSubscriptionCount: number;
}

const MigrationInProgressNotice: React.FC< Props > = ( {
	stripeBillingSubscriptionCount,
} ) => {
	const [ isDismissed, setIsDismissed ] = useState( false );

	const context = useContext( StripeBillingMigrationNoticeContext );

	// Don't show the notice if it has been dismissed.
	if ( isDismissed ) {
		return null;
	}

	// Don't show the notice if the migration option is shown.
	if ( context.isMigrationOptionShown ) {
		return null;
	}

	// Don't show the notice if no migration in progress.
	if ( ! context.isMigrationInProgress ) {
		return null;
	}

	// Mark the notice as shown.
	context.isMigrationInProgressShown = true;

	const content = sprintf(
		_n(
			'%d customer subscription is being migrated from Stripe off-site billing to billing powered by' +
				' %s and %s.',
			'%d customer subscriptions are being migrated from Stripe off-site billing to billing powered by' +
				' %s and %s.',
			stripeBillingSubscriptionCount,
			'woocommerce-payments'
		),
		stripeBillingSubscriptionCount,
		'Woo Subscriptions',
		'WooPayments'
	);

	return (
		<InlineNotice
			status="info"
			isDismissible={ true }
			onRemove={ () => setIsDismissed( true ) }
			className="woopayments-stripe-billing-notice"
		>
			<Loadable
				isLoading={ false }
				display="inline"
				placeholder={ content }
				value={ content }
			/>
		</InlineNotice>
	);
};

export default MigrationInProgressNotice;
