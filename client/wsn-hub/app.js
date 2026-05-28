/**
 * WsnHubApp — the top-level component for the Woo Shopping Network Hub page.
 *
 * Hosts the 3-tab framework (Overview / Visibility / Profile) using @wordpress/components'
 * <TabPanel> with hash-based deep-linking so /payments/shopping-network#visibility lands
 * on the Visibility tab directly.
 *
 * Tab content for each tab is a stub at this stage — real content ships in
 * RSM-2493 (Overview), RSM-2480 (Visibility), and RSM-2481 (Profile).
 *
 * Note on the v2 mockup's "tabs hidden when disabled" rule: the mockup pre-enable
 * hero is owned by the Overview tab (RSM-2493) and the enable/disable transition
 * is wired there. This scaffolding renders the framework as if always enabled, so
 * the tab nav is always visible. RSM-2493 will reintroduce the conditional hide.
 *
 * @format
 */

import { useEffect, useState } from '@wordpress/element';
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import PageHeader from './page-header';
import OverviewTab from './overview-tab';
import VisibilityTab from './visibility-tab';
import ProfileTab from './profile-tab';
import { colors, spacing } from './tokens';

const TABS = [
	{ name: 'overview', title: __( 'Overview', 'woocommerce-payments' ) },
	{ name: 'visibility', title: __( 'Visibility', 'woocommerce-payments' ) },
	{ name: 'profile', title: __( 'Profile', 'woocommerce-payments' ) },
];

const TAB_NAMES = TABS.map( ( tab ) => tab.name );

const getInitialTabName = () => {
	if ( typeof window === 'undefined' ) {
		return TABS[ 0 ].name;
	}
	const hash = window.location.hash.replace( '#', '' );
	return TAB_NAMES.includes( hash ) ? hash : TABS[ 0 ].name;
};

const WsnHubApp = () => {
	// useState seeds from the hash on first render; subsequent hash changes update via
	// the effect below so back/forward navigation works.
	const [ currentTab, setCurrentTab ] = useState( getInitialTabName );

	useEffect( () => {
		const handler = () => setCurrentTab( getInitialTabName() );
		window.addEventListener( 'hashchange', handler );
		return () => window.removeEventListener( 'hashchange', handler );
	}, [] );

	const onSelect = ( tabName ) => {
		setCurrentTab( tabName );
		if ( typeof window !== 'undefined' ) {
			window.location.hash = tabName;
		}
	};

	return (
		<div className="wcpay-wsn-hub">
			<PageHeader />
			<TabPanel
				className="wcpay-wsn-hub__tabs"
				tabs={ TABS }
				initialTabName={ currentTab }
				onSelect={ onSelect }
			>
				{ ( tab ) => (
					<div
						style={ {
							padding: `${ spacing.s4 } ${ spacing.s3 } 0`,
							background: colors.surface,
						} }
					>
						{ tab.name === 'overview' && <OverviewTab /> }
						{ tab.name === 'visibility' && <VisibilityTab /> }
						{ tab.name === 'profile' && <ProfileTab /> }
					</div>
				) }
			</TabPanel>
		</div>
	);
};

export default WsnHubApp;
