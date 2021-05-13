/** @format */
/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import './settings-section.scss';

const SettingsSection = ( { Description = () => null, children } ) => (
	<div className="settings-section">
		<div className="settings-section__details">
			<Description />
		</div>
		<div className="settings-section__controls">{ children }</div>
	</div>
);

export default SettingsSection;
