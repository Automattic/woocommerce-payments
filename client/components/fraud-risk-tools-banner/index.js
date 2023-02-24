/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { BannerBody, NewPill, BannerActions } from './components';
import './style.scss';

const FRTDiscoverabilityBanner = () => {
	return (
		<Card>
			<div className="discoverability-card">
				<NewPill />
				<h3 className="discoverability-card__header">
					{ __(
						'Enhanced fraud protection for your store',
						'woocommerce-payments'
					) }
				</h3>
				<BannerBody />
				<BannerActions />
			</div>
		</Card>
	);
};

export default FRTDiscoverabilityBanner;
