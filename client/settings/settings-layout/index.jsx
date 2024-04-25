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

const SettingsLayout = ( { children, displayBanner = true } ) => (
	<div className="wcpay-settings-layout">
		{ displayBanner && <Banner /> }

		{ children }
	</div>
);

export default SettingsLayout;
