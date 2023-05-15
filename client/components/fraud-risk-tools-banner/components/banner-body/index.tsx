/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

const BannerBody: React.FC = () => {
	return (
		<p className="discoverability-card__body">
			{ __(
				'New features have been added to WooPayments to help reduce fraudulent transactions on your store. ' +
					'By using a set of rules to evaluate incoming orders, your store is better protected from fraudsters.',
				'woocommerce-payments'
			) }
		</p>
	);
};

export default BannerBody;
