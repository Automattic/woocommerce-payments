/** @format */

import React from 'react';
import { __ } from '@wordpress/i18n';

import type { ReportsTab } from './types';
import { FeesExportButton } from './fees-export-button';

interface ReportsHeaderProps {
	activeTab: ReportsTab;
}

export const ReportsHeader: React.FC< ReportsHeaderProps > = ( {
	activeTab,
} ) => {
	return (
		<div className="wcpay-reports-header">
			<h1 className="screen-reader-text">
				{ __( 'Reports', 'woocommerce-payments' ) }
			</h1>
			<div className="wcpay-reports-header__intro">
				<p>
					{ __(
						'View your reconciliation reports.',
						'woocommerce-payments'
					) }
				</p>
			</div>
			{ activeTab === 'fees' && (
				<div className="wcpay-reports-header__actions">
					<FeesExportButton />
				</div>
			) }
		</div>
	);
};
