/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies.
 */
import TotalPaymentsVolume from './total-payments-volume';
import PaymentsDataHighlights from './payments-data-highlights';

import './style.scss';

const PaymentsActivityData: React.FC = () => {
	return (
		<div className="wcpay-payments-activity-data">
			<TotalPaymentsVolume />
			<PaymentsDataHighlights />
		</div>
	);
};

export default PaymentsActivityData;
