/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Status } from '@wordpress/notices';

export interface CapabilityStatus {
	status: Status;
	content: string;
	actions?: string;
	actionsLabel?: string;
	actionUrl?: string;
}

export interface CapabilityRequestMap {
	id: string;
	country?: string;
	states: Record< string, CapabilityStatus >;
}

const CapabilityRequestList: Array< CapabilityRequestMap > = [
	{
		id: 'jcb',
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
				actionUrl: 'http://google.com',
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
					'The JCB payment method is now enabled on your store.',
					'woocommerce-payments'
				),
			},
		},
	},
];

export default CapabilityRequestList;
