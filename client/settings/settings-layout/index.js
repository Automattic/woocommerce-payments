/** @format */
/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import Banner from '../../banner';
import './style.scss';

const SettingsLayout = ( { children } ) => (
	<div className="wcpay-settings-layout">
		<Banner />

		{ children }
	</div>
);

export default SettingsLayout;
