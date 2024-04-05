/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies.
 */
import PaymentsDataHighlights from './payments-data-highlights';
import PaymentsDataTile from './payments-data-tile';
import { paymentsDataHighlightsStrings } from './strings';
import { TotalPaymentsVolumeTooltip } from './payments-data-highlights-tooltips';

import './style.scss';

const PaymentsActivityData: React.FC = () => {
	return (
		<div className="wcpay-payments-activity-data">
			<PaymentsDataTile
				id="wcpay-payments-activity-data__total-payments-volume"
				title={ paymentsDataHighlightsStrings.totalPaymentsVolume }
				currencyCode="USD"
				amount={ 156373 }
				tooltip={ <TotalPaymentsVolumeTooltip /> }
			/>
			<PaymentsDataHighlights />
		</div>
	);
};

export default PaymentsActivityData;
