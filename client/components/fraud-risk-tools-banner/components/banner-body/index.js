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
					'New features have been added to WooCommerce Payments to help {{strong}}reduce fraudulent ' +
						'transactions{{/strong}} on your store. By using a set of rules to evaluate incoming orders, ' +
						'your store is better protected from fraudsters.',
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
