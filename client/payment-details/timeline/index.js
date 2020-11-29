/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card, Timeline } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import { useTimeline } from 'data';
import mapTimelineEvents from './map-events';
import Loadable, { LoadableBlock } from 'components/loadable';

const PaymentDetailsTimeline = ( { chargeId } ) => {
	const { timeline, timelineError, isLoading } = useTimeline( chargeId );
	const items = mapTimelineEvents( timeline );

	return (
		<Card
			title={
				<Loadable
					isLoading={ isLoading }
					value={ __( 'Timeline', 'woocommerce-payments' ) }
				/>
			}
			className="payment-details__timeline"
		>
			<LoadableBlock isLoading={ isLoading } numLines={ 3 }>
				{ timelineError instanceof Error ? (
					__( 'Error while loading timeline', 'woocommerce-payments' )
				) : (
					<Timeline items={ items } />
				) }
			</LoadableBlock>
			<LoadableBlock isLoading={ isLoading } numLines={ 3 } />
			<LoadableBlock isLoading={ isLoading } numLines={ 3 } />
			<LoadableBlock isLoading={ isLoading } numLines={ 3 } />
		</Card>
	);
};

export default PaymentDetailsTimeline;
