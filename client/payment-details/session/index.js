/** @format **/

/**
 * External dependencies
 */
import { Card } from '@woocommerce/components';

/**
 * Internal dependencies.
 */

const PaymentDetailsSession = ( props ) => {
	const { transaction } = props;
	// TODO: this is a placeholder card and does not require translation
	return (
		<Card title="Session">
			Session details for transaction { transaction.id }.
		</Card>
	);
};

export default PaymentDetailsSession;
