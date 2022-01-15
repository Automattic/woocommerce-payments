/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { addQueryArgs } from '@wordpress/url';

const JetpackIdcNotice = () => {
	return (
		wcpaySettings.hasOwnProperty( 'isJetpackIdcActive' ) &&
		wcpaySettings.isJetpackIdcActive && (
			<Notice
				status="error"
				isDismissible={ false }
				className="wcpay-jetpack-idc-notice"
			>
				{ __(
					'Your site is currently in Safe Mode.',
					'woocommerce-payments'
				) }
				<span>&nbsp;</span>
				<a href={ addQueryArgs( '/wp-admin/index.php' ) }>
					{ __( 'Please take action!', 'woocommerce-payments' ) }
				</a>
			</Notice>
		)
	);
};

export default JetpackIdcNotice;
