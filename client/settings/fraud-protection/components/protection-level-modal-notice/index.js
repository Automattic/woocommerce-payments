/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import TipIcon from 'wcpay/icons';
import { ProtectionLevel } from '../../advanced-settings/constants';

const ProtectionLevelModalNotice = ( { level } ) => {
	const isHighProtectionLevel = ProtectionLevel.HIGH === level;

	return (
		<Notice
			className="component-notice--is-info"
			status="info"
			isDismissible={ false }
		>
			<div className="component-notice__content--flex">
				<TipIcon className="component-notice__icon" />
				<p>
					{ isHighProtectionLevel
						? __(
								'Offers the highest level of filtering for stores, but may catch some legitimate transactions.',
								'woocommerce-payments'
						  )
						: __(
								"Provides a standard level of filtering that's suitable for most business.",
								'woocommerce-payments'
						  ) }
				</p>
			</div>
		</Notice>
	);
};

export default ProtectionLevelModalNotice;
