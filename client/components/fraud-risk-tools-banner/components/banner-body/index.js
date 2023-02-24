/**
 * External dependencies
 */
import interpolateComponents from 'interpolate-components';
import { __ } from '@wordpress/i18n';

const BannerBody = () => {
	return (
		<p className="discoverability-card__body">
			{ interpolateComponents( {
				mixedString: __(
					'A new range of tools have been added to WooCommerce Payments to help {{strong}}reduce fraudulent ' +
						'transactions{{/strong}} on your store. In addition to purchase verification, new risk filters ' +
						'are now available to screen incoming transactions - better protecting your store from fraudsters.',
					'woocommerce-payments'
				),
				components: {
					strong: <strong />,
				},
			} ) }
		</p>
	);
};

export default BannerBody;
