/**
 * External dependencies
 */
import React from 'react';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';

const BannerBody: React.FC = () => {
	return (
		<p className="discoverability-card__body">
			{ interpolateComponents( {
				mixedString: __(
					'New features have been added to WooCommerce Payments to help {{strong}}reduce fraudulent ' +
						'transactions{{/strong}} on your store. By using a set of customizable rules to evaluate incoming orders, ' +
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
