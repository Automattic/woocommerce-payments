/** @format **/

/**
 * Internal dependencies.
 */
import { Card } from '@woocommerce/components';
import { Timeline } from '@woocommerce/components';

const PaymentDetailsTimeline = ( props ) => {
	const { charge } = props;

	// Dummy data for validating approach, work in progress
	// TODO: Synthesize what timeline events we can from the charge object OR
	// fetch them from an events endpoint
	const items = [];
	items.push(
		{
			datetime: 1603900800,
			gridicon: 'checkmark',
			headline: 'A payment of $97.28 was successfully charged',
			body: [
				'Fee: $3.12 (2.9% + $0.30)',
				'Net deposit: $94.16',
			],
		},
		{
			datetime: 1603900800,
			gridicon: 'plus-small',
			headline: '$94.16 was added to your <a href="">October 29, 2019</a> deposit',
			body: [],
		},
		{
			datetime: 1603854540,
			gridicon: 'checkmark',
			headline: 'A payment of $97.28 was successfully authorized',
			body: [],
		},
	);

	// TODO: this is a placeholder card and does not require translation
	return (
		<Card title="Timeline">
			<Timeline items={ items }/>
		</Card>
	);
};

export default PaymentDetailsTimeline;
