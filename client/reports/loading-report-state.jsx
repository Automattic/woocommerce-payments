/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

export const LoadingReportState = ( { headingRef, headingTabIndex } = {} ) => (
	<div
		className="wcpay-reports-state wcpay-reports-state--loading"
		role="status"
	>
		<h2 ref={ headingRef } tabIndex={ headingTabIndex }>
			{ __( 'Loading report', 'woocommerce-payments' ) }
		</h2>
	</div>
);
