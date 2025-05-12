/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Timeline } from '@woocommerce/components';
import {
	Card,
	CardBody,
	CardHeader,
} from 'wcpay/components/wp-components-wrapped';

/**
 * Internal dependencies
 */
import { useTimeline } from 'wcpay/data';
import mapTimelineEvents from './map-events';
import Loadable, { LoadableBlock } from 'components/loadable';

import './style.scss';

const PaymentDetailsTimeline = ( {
	paymentIntentId,
	bankName,
	shouldUseBundledComponents,
} ) => {
	const { timeline, timelineError, isLoading } = useTimeline(
		paymentIntentId
	);

	const items = mapTimelineEvents( timeline, bankName );

	return (
		<Card useBundledComponent={ shouldUseBundledComponents } size="large">
			<CardHeader useBundledComponent={ shouldUseBundledComponents }>
				<Loadable
					isLoading={ isLoading }
					value={ __( 'Timeline', 'woocommerce-payments' ) }
				/>
			</CardHeader>
			<CardBody useBundledComponent={ shouldUseBundledComponents }>
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
