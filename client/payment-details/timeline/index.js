/** @format **/

/**
 * Internal dependencies.
 */
import { Card } from '@woocommerce/components';
import { Timeline } from '@woocommerce/components';

const PaymentDetailsTimeline = ( props ) => {
	const { charge } = props;
	// TODO: this is a placeholder card and does not require translation
	return (
		<Card title="Timeline">
			Timeline details for charge { charge.id }.
			<Timeline />
		</Card>
	);
};

export default PaymentDetailsTimeline;
