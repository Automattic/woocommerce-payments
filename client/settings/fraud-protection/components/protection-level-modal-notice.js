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

const ProtectionLevelModalNotice = ( { level } ) => {
	if ( 'high' === level ) {
		return (
			<Notice
				className="component-notice--is-info"
				status="info"
				isDismissible={ false }
			>
				<div className="component-notice__content--flex">
					<TipIcon className="component-notice__icon" />
					<p>
						{ __(
							'Offers the highest level of filtering for stores, but may catch some legitimate transactions',
							'woocommerce-payments'
						) }
					</p>
				</div>
			</Notice>
		);
	}

	return (
		<Notice
			className="component-notice--is-info"
			status="info"
			isDismissible={ false }
		>
			<div className="component-notice__content--flex">
				<TipIcon className="component-notice__icon" />
				<p>
					{ __(
						"Provides a standard level of filtering that's suitable for most business.",
						'woocommerce-payments'
					) }
				</p>
			</div>
		</Notice>
	);
};

export default ProtectionLevelModalNotice;
