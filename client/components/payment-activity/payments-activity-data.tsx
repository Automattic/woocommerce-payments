/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import TotalPaymentsVolume from './total-payments-volume';
import PaymentsDataHighlights from './payments-data-highlights';

import './style.scss';

const PaymentsActivityData: React.FC = () => {
	return (
		<div className="payments-activity-data">
			<TotalPaymentsVolume />
			<PaymentsDataHighlights />
		</div>
	);
};

export default PaymentsActivityData;
