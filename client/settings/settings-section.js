/** @format */

/**
 * Internal dependencies
 */
import './settings-section.scss';

const SettingsSection = ( { title, description, children } ) => (
	<div className="settings-section">
		<div className="settings-section__details">
			<h2>{ title }</h2>
			<p>{ description }</p>
		</div>
		<div className="settings-section__controls">{ children }</div>
	</div>
);

export default SettingsSection;
