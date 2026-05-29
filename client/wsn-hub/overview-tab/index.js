/**
 * OverviewTab — switches between the pre-enable hero and the post-enable
 * dashboard based on `wcpaySettings.wsn?.enabled` (read once on mount,
 * mutated locally on enable/disable so the user doesn't have to refresh).
 *
 * The v2 mockup treats the pre-enable hero as the only surface visible to
 * a never-enabled merchant — tabs nav is hidden at the app shell level
 * (see `app.js`), so the merchant only sees this hero until they enable.
 * Once enabled, the dashboard renders inside the tab the user is already on.
 *
 * Owned by RSM-2493.
 *
 * @format
 */

import { useState } from '@wordpress/element';

import PreEnableHero from './pre-enable-hero';
import OverviewDashboard from './overview-dashboard';

const OverviewTab = ( { isEnabled, onEnabledChange } ) => {
	// Local state so enable/disable transitions don't require a page reload.
	// Seed from the parent-supplied prop on first render; parent owns the
	// canonical state and re-renders both this tab AND the app shell tab nav
	// when the value changes.
	const [ enabled, setEnabled ] = useState( !! isEnabled );

	const handleEnabled = () => {
		setEnabled( true );
		onEnabledChange?.( true );
	};

	const handleDisabled = () => {
		setEnabled( false );
		onEnabledChange?.( false );
	};

	if ( ! enabled ) {
		return <PreEnableHero onEnabled={ handleEnabled } />;
	}
	return <OverviewDashboard onDisable={ handleDisabled } />;
};

export default OverviewTab;
