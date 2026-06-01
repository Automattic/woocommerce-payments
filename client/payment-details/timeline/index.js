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
import { useTimeline } from 'wcpay/data';
import mapTimelineEvents from './map-events';
import Loadable, { LoadableBlock } from 'components/loadable';

import './style.scss';

const PaymentDetailsTimeline = ( { paymentIntentId, bankName } ) => {
	const { timeline, timelineError, isLoading } =
		useTimeline( paymentIntentId );

	const items = mapTimelineEvents( timeline, bankName );

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
						<Timeline
							items={ items }
							groupBy="day"
							orderBy="desc"
							/* translators: PHP date format string used to display dates, see php.net/date. */
							dateFormat={ __(
								'F j, Y',
								'woocommerce-payments'
							) }
							/* translators: PHP clock format string used to display times, see php.net/date. */
							clockFormat={ __( 'g:ia', 'woocommerce-payments' ) }
						/>
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
