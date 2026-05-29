/**
 * OverviewTab — stateless dispatcher between the pre-enable hero and the
 * post-enable dashboard. Reads `isEnabled` from props (canonical state lives
 * in `WsnHubApp`); when the merchant enables/disables, the child views call
 * `onEnabledChange` to notify the parent, which re-renders this component
 * with the new prop.
 *
 * Owned by RSM-2493.
 *
 * @format
 */

import PreEnableHero from './pre-enable-hero';
import OverviewDashboard from './overview-dashboard';

const OverviewTab = ( { isEnabled, onEnabledChange } ) => {
	if ( ! isEnabled ) {
		return <PreEnableHero onEnabled={ () => onEnabledChange?.( true ) } />;
	}
	return <OverviewDashboard onDisable={ () => onEnabledChange?.( false ) } />;
};

export default OverviewTab;
