/**
 * External dependencies
 */
import { Fill } from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
/**
 * Internal dependencies
 */
import FRTDiscoverabilityBanner from '.';

const WooHomescreenFRTBanner = () => (
	<Fill name="woocommerce_homescreen_experimental_header_banner_item">
		<FRTDiscoverabilityBanner />
	</Fill>
);

registerPlugin( 'woocommerce-fraud-protection-banner', {
	render: WooHomescreenFRTBanner,
	scope: 'woocommerce-admin',
} );
