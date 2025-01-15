/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import React from 'react';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getAdminUrl } from '../../utils';
import { STORE_NAME } from 'wcpay/data/constants';
import InlineNotice from '../inline-notice';

const AppleGooglePayShippingSettingsCompatibilityNotice = () => {
	const isRequiringFullAddressAtCheckout = useSelect( ( select ) =>
		select( STORE_NAME ).getIsRequiringFullAddressAtCheckout()
	);

	if ( ! isRequiringFullAddressAtCheckout ) return null;

	return (
		<InlineNotice status="warning" icon={ true } isDismissible={ false }>
			{ interpolateComponents( {
				mixedString: __(
					'The your shipping settings can cause conflicts with this payment method. To resolve this issue, please disable the "Hide shipping costs until an address is entered" option {{link}}in the shipping settings page{{/link}}.',
					'woocommerce-payments'
				),
				components: {
					link: (
						<a
							href={ getAdminUrl( {
								page: 'wc-settings',
								tab: 'shipping',
								section: 'options',
							} ) }
						>
							Review extensions
						</a>
					),
				},
			} ) }
		</InlineNotice>
	);
};

export default AppleGooglePayShippingSettingsCompatibilityNotice;
