/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { CapabilityRequestMap } from './types';

const CapabilityRequestList: Array< CapabilityRequestMap > = [
	{
		id: 'jcb',
		label: __( 'JCB', 'woocommerce-payments' ),
		country: 'JP',
		states: {
			unrequested: {
				status: 'info',
				content: __(
					'Enable JCB for your customers, the only international payment brand based in Japan.',
					'woocommerce-payments'
				),
				actions: 'request',
				actionsLabel: __( 'Enable JCB', 'woocommerce-payments' ),
			},
			pending_verification: {
				status: 'warning',
				content: __(
					'To enable JCB for your customers, you need to provide more information.',
					'woocommerce-payments'
				),
				actions: 'link',
				actionUrl:
					'https://woo.com/document/woopayments/payment-methods/#jcb',
				actionsLabel: __( 'Finish setup', 'woocommerce-payments' ),
			},
			pending: {
				status: 'info',
				content: __(
					'Your information has been submitted and your JCB account is pending approval.',
					'woocommerce-payments'
				),
			},
			inactive: {
				status: 'info',
				content: __(
					'Your JCB account was rejected based on the information provided.',
					'woocommerce-payments'
				),
			},
			active: {
				status: 'info',
				content: __(
					'JCB is now enabled on your store.',
					'woocommerce-payments'
				),
			},
		},
	},
];

export default CapabilityRequestList;
