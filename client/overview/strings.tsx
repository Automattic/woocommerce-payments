/* eslint-disable max-len */
/**
 * External dependencies
 */
import interpolateComponents from '@automattic/interpolate-components';
import { __, sprintf } from '@wordpress/i18n';
import React from 'react';

export default {
	notice: {
		content: {
			test: interpolateComponents( {
				mixedString: sprintf(
					/* translators: %1$s: WooPayments */
					__(
						'{{bold}}%1s is in test mode.{{bold /}}. All transactions will be simulated.',
						'woocommerce-payments'
					),
					'WooPayments'
				),
				components: {
					bold: <b />,
				},
			} ),
			dev: interpolateComponents( {
				mixedString: sprintf(
					/* translators: %1$s: WooPayments */
					__(
						'{{bold}}%1s is in dev mode.{{bold /}}. You need to set up a live %1s account before you can accept real transactions.',
						'woocommerce-payments'
					),
					'WooPayments'
				),
				components: {
					bold: <b />,
				},
			} ),
		},
		actions: {
			goLive: __( 'Ready to go live?', 'woocommerce-payments' ),
			setUpPayments: __( 'Set up payments', 'woocommerce-payments' ),
			learnMore: __( 'Learn more', 'woocommerce-payments' ),
		},
	},
};
