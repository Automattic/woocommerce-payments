/** @format */

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */

const AccountStatusItem = ( props ) => {
	const { label, value } = props;

	return (
		<div className="woocommerce-account-status-item">
			<div className="woocommerce-account-status-item-label">
				{ label }
			</div>
			<div className="woocommerce-account-status-item-value">
				{ value }
			</div>
		</div>
	);
};

export default AccountStatusItem;
