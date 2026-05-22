/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useId, useRef } from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { ReportsTab, ReportsTabStatus } from './types';
import { LazyLoadedFeesReport, LoadingReportState } from './lazy-fees-report';

interface ReportsTabPanelProps {
	tab: ReportsTab;
	status: ReportsTabStatus;
	onReload: () => void;
}

export const reportsTabs: Array< {
	name: ReportsTab;
	title: string;
	className: string;
} > = [
	{
		name: 'balance',
		title: __( 'Balance', 'woocommerce-payments' ),
		className: 'wcpay-reports-tab--balance',
	},
	{
		name: 'fees',
		title: __( 'Fees', 'woocommerce-payments' ),
		className: 'wcpay-reports-tab--fees',
	},
];

export function normalizeReportsTab( tab?: unknown ): ReportsTab {
	return tab === 'fees' ? 'fees' : 'balance';
}

// `getEmptyContent` only needs to handle `tab === 'balance'` here — the Fees
// tab owns its own loading/error/empty UI inside `<FeesReport>` and
// short-circuits before reaching those branches.
function getEmptyContent(): {
	title: string;
	description: string;
} {
	return {
		title: __( 'No balance activity', 'woocommerce-payments' ),
		description: __(
			"Your Balance summary will appear here once there's enough data to display.",
			'woocommerce-payments'
		),
	};
}

export const ReportsTabPanel: React.FC< ReportsTabPanelProps > = ( {
	tab,
	status,
	onReload,
} ) => {
	const contentHeadingRef = useRef< HTMLHeadingElement >( null );
	const previousStatusRef = useRef< ReportsTabStatus >( status );
	const headingId = useId();

	useEffect( () => {
		const previousStatus = previousStatusRef.current;

		if (
			previousStatus !== status &&
			( previousStatus === 'error' || status === 'error' )
		) {
			contentHeadingRef.current?.focus();
		}

		previousStatusRef.current = status;
	}, [ status ] );

	if ( tab === 'fees' ) {
		return <LazyLoadedFeesReport onReload={ onReload } />;
	}

	if ( status === 'loading' ) {
		return (
			<LoadingReportState
				headingRef={ contentHeadingRef }
				headingTabIndex={ -1 }
			/>
		);
	}

	if ( status === 'error' ) {
		return (
			<div
				className="wcpay-reports-state wcpay-reports-state--error"
				role="group"
				aria-labelledby={ headingId }
			>
				<h2 id={ headingId } ref={ contentHeadingRef } tabIndex={ -1 }>
					{ __( 'Balance unavailable', 'woocommerce-payments' ) }
				</h2>
				<Button variant="secondary" onClick={ onReload }>
					{ __( 'Reload report', 'woocommerce-payments' ) }
				</Button>
			</div>
		);
	}

	const { title, description } = getEmptyContent();

	return (
		<div className="wcpay-reports-state wcpay-reports-state--empty">
			<h2 ref={ contentHeadingRef } tabIndex={ -1 }>
				{ title }
			</h2>
			<p>{ description }</p>
		</div>
	);
};
