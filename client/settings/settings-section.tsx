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
	/* eslint-disable-next-line @typescript-eslint/naming-convention */
	Description?: React.FunctionComponent;
	children?: React.ReactNode;
	className?: string;
	title?: string;
} > = ( { Description = () => null, children, className } ) => (
	<div className={ classNames( 'settings-section', className ) }>
		<div className="settings-section__details">
			<Description />
		</div>
		<div className="settings-section__controls">{ children }</div>
	</div>
);

export default SettingsSection;
