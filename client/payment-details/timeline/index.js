/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Timeline } from '@woocommerce/components';
import { Card, CardBody, CardHeader } from '@wordpress/components';
/**
 * Internal dependencies
 */
import { useTimeline } from 'wcpay/data/timeline';
import mapTimelineEvents from './map-events';
// MOCKUP (EFW #304): remove with the rest of the EFW mock when the real feature lands.
import { injectMockEfwEvent } from './efw-mock';
import Loadable, { LoadableBlock } from 'components/loadable';

import './style.scss';

const PaymentDetailsTimeline = ( { paymentIntentId, bankName } ) => {
	const { timeline, timelineError, isLoading } =
		useTimeline( paymentIntentId );

	const items = mapTimelineEvents(
		// MOCKUP (EFW #304): splice in a synthetic early-fraud-warning event.
		injectMockEfwEvent( timeline, paymentIntentId ),
		bankName
	);

	return (
		<Card size="large">
			<CardHeader>
				<Loadable
					isLoading={ isLoading }
					value={ __( 'Timeline', 'woocommerce-payments' ) }
				/>
			</CardHeader>
			<CardBody>
				<LoadableBlock isLoading={ isLoading } numLines={ 3 }>
					{ timelineError instanceof Error ? (
						__(
							'Error while loading timeline',
							'woocommerce-payments'
						)
					) : (
						<Timeline items={ items } />
					) }
				</LoadableBlock>
				<LoadableBlock isLoading={ isLoading } numLines={ 3 } />
				<LoadableBlock isLoading={ isLoading } numLines={ 3 } />
				<LoadableBlock isLoading={ isLoading } numLines={ 3 } />
			</CardBody>
		</Card>
	);
};

export default PaymentDetailsTimeline;
