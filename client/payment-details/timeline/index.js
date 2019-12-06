/** @format **/

/**
 * External dependencies
 */
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */

const PaymentDetailsTimeline = ( props ) => {
	const { transaction } = props;
	// TODO: this is a placeholder card and does not require translation
	return (
		<Card title="Timeline">
			Timeline details for transaction { transaction.id }.
		</Card>
	);
};

export default PaymentDetailsTimeline;
