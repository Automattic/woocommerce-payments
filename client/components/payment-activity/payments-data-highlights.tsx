/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies.
 */
import './style.scss';
import PaymentsDataHighlightBlock from './payments-data-highlight-block';
import { paymentsDataHighlightsStrings } from './strings';

const PaymentsDataHighlights: React.FC = () => {
	return (
		<>
			<div className="payments-data-highlights">
				<PaymentsDataHighlightBlock
					id="payments-data-highlights__charges"
					title={ paymentsDataHighlightsStrings.charges }
					currencyCode="USD"
					amount={ 123456 }
				/>
				<PaymentsDataHighlightBlock
					id="payments-data-highlights__refunds"
					title={ paymentsDataHighlightsStrings.refunds }
					currencyCode="USD"
					amount={ 123456 }
				/>
				<PaymentsDataHighlightBlock
					id="payments-data-highlights__disputes"
					title={ paymentsDataHighlightsStrings.disputes }
					currencyCode="USD"
					amount={ 123456 }
				/>
				<PaymentsDataHighlightBlock
					id="payments-data-highlights__fees"
					title={ paymentsDataHighlightsStrings.fees }
					currencyCode="USD"
					amount={ 123456 }
				/>
			</div>
		</>
	);
};

export default PaymentsDataHighlights;
