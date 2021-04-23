/** @format */

const SettingsSection = ( { title, description, children } ) => (
	<div className="settings-manager__section">
		<div className="settings-manager__section-details">
			<h2>{ title }</h2>
			<p>{ description }</p>
		</div>
		<div className="settings-manager__controls">{ children }</div>
	</div>
);

export default SettingsSection;
