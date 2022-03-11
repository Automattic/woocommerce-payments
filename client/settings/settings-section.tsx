/** @format */
/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import './settings-section.scss';

const SettingsSection: React.FunctionComponent< {
	children?: React.ReactNode;
	className?: string;
	description?: React.FunctionComponent;
	title?: string;
} > = ( { description: Description = () => null, children, className } ) => (
	<div className={ classNames( 'settings-section', className ) }>
		<div className="settings-section__details">
			<Description />
		</div>
		<div className="settings-section__controls">{ children }</div>
	</div>
);

export default SettingsSection;
