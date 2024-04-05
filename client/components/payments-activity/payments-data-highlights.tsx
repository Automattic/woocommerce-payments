/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies.
 */
import './style.scss';
import PaymentsDataTile from './payments-data-tile';
import { paymentsDataHighlightsStrings } from './strings';
import {
	PaymentsDataChargeTooltip,
	PaymentsDataFeesTooltip,
} from './payments-data-highlights-tooltips';

const PaymentsDataHighlights: React.FC = () => {
	return (
		<>
			<div className="wcpay-payments-data-highlights">
				<PaymentsDataTile
					id="wcpay-payments-data-highlights__charges"
					title={ paymentsDataHighlightsStrings.charges }
					currencyCode="USD"
					amount={ 123456 }
					tooltip={ <PaymentsDataChargeTooltip /> }
				/>
				<PaymentsDataTile
					id="wcpay-payments-data-highlights__refunds"
					title={ paymentsDataHighlightsStrings.refunds }
					currencyCode="USD"
					amount={ 123456 }
				/>
				<PaymentsDataTile
					id="wcpay-payments-data-highlights__disputes"
					title={ paymentsDataHighlightsStrings.disputes }
					currencyCode="USD"
					amount={ 123456 }
				/>
				<PaymentsDataTile
					id="wcpay-payments-data-highlights__fees"
					title={ paymentsDataHighlightsStrings.fees }
					currencyCode="USD"
					amount={ 123456 }
					tooltip={ <PaymentsDataFeesTooltip /> }
				/>
			</div>
		</>
	);
};

export default PaymentsDataHighlights;
