/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { ReportsTab, ReportsTabStatus } from './types';

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

function getEmptyContent( tab: ReportsTab ): {
	title: string;
	description?: string;
} {
	if ( tab === 'fees' ) {
		return {
			title: __( 'No fees yet', 'woocommerce-payments' ),
		};
	}

	return {
		title: __( 'No balance activity', 'woocommerce-payments' ),
		description: __(
			"Your Balance summary will appear here once there's enough data to display.",
			'woocommerce-payments'
		),
	};
}

function getErrorTitle( tab: ReportsTab ): string {
	return tab === 'fees'
		? __( 'Fees report unavailable', 'woocommerce-payments' )
		: __( 'Balance unavailable', 'woocommerce-payments' );
}

export const ReportsTabPanel: React.FC< ReportsTabPanelProps > = ( {
	tab,
	status,
	onReload,
} ) => {
	if ( status === 'error' ) {
		return (
			<div
				className="wcpay-reports-state wcpay-reports-state--error"
				role="alert"
			>
				<h2>{ getErrorTitle( tab ) }</h2>
				<Button variant="secondary" onClick={ onReload }>
					{ __( 'Reload report', 'woocommerce-payments' ) }
				</Button>
			</div>
		);
	}

	const { title, description } = getEmptyContent( tab );

	return (
		<div className="wcpay-reports-state wcpay-reports-state--empty">
			<h2>{ title }</h2>
			{ description && <p>{ description }</p> }
		</div>
	);
};
