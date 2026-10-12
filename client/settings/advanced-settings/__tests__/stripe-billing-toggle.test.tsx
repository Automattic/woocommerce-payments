/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import StripeBillingToggle from '../stripe-billing-toggle';
import StripeBillingMigrationNoticeContext from '../stripe-billing-notices/context';

const renderToggle = ( contextOverrides = {} ) =>
	render(
		<StripeBillingMigrationNoticeContext.Provider
			value={ {
				isStripeBillingEnabled: false,
				savedIsStripeBillingEnabled: false,
				isMigrationOptionShown: false,
				isMigrationInProgress: false,
				hasSavedSettings: false,
				subscriptionCount: 0,
				migratedCount: 0,
				startMigration: jest.fn(),
				isResolvingMigrateRequest: false,
				hasResolvedMigrateRequest: false,
				setIsMigrationInProgress: jest.fn(),
				...contextOverrides,
			} }
		>
			<StripeBillingToggle onChange={ jest.fn() } />
		</StripeBillingMigrationNoticeContext.Provider>
	);

describe( 'StripeBillingToggle', () => {
	it.each( [
		[ 'default', {} ],
		[ 'migration option', { isMigrationOptionShown: true } ],
	] )(
		'discloses the Stripe Billing fee in the %s copy',
		( variant, context ) => {
			renderToggle( context );

			expect(
				screen.getByText(
					/Stripe Billing, which charges a 1% fee on the full total of any order containing a subscription\./
				)
			).toBeInTheDocument();
			expect(
				screen.getByRole( 'link', { name: /Learn more about fees/ } )
			).toHaveAttribute(
				'href',
				'https://woocommerce.com/document/woopayments/fees/'
			);
		}
	);
} );
