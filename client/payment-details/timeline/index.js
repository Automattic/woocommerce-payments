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

const PaymentDetailsTimeline = ( { chargeId } ) => {
	const { timeline, timelineError, isLoading } = useTimeline( chargeId );

	const items = mapTimelineEvents( timeline );

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
