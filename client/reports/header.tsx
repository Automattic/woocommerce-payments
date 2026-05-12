/** @format */

import React from 'react';
import { __ } from '@wordpress/i18n';

export const ReportsHeader: React.FC = () => {
	return (
		<div className="wcpay-reports-header">
			<h1 className="screen-reader-text">
				{ __( 'Reports', 'woocommerce-payments' ) }
			</h1>
			<p>
				{ __(
					'View your reconciliation reports.',
					'woocommerce-payments'
				) }
			</p>
		</div>
	);
};
