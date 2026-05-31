/**
 * WsnHubApp — the top-level component for the Woo Shopping Network Hub page.
 *
 * Hosts the 3-tab framework (Overview / Visibility / Profile) using @wordpress/components'
 * <TabPanel> with hash-based deep-linking so /shopping-network#visibility lands on the
 * Visibility tab directly.
 *
 * Pre-enable state (RSM-2493): when `wcpaySettings.wsn?.enabled` is falsy, the tab nav
 * is hidden and only the Overview tab's PreEnableHero renders, taking over the full
 * page. The merchant clicks "Enable Woo Shopping Network" on the hero, which flips
 * the option via the settings PUT endpoint. On success, OverviewTab calls
 * `onEnabledChange(true)` which re-renders this shell with the tab nav visible.
 *
 * Visibility and Profile tab content lands in RSM-2480 and RSM-2481 respectively.
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
import { TabErrorBoundary } from './utils/error-boundary';

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

const getInitialEnabled = () => {
	if ( typeof window === 'undefined' ) {
		return false;
	}
	return Boolean( window.wcpaySettings?.wsn?.enabled );
};

const WsnHubApp = () => {
	// useState seeds from the hash on first render; subsequent hash changes update via
	// the effect below so back/forward navigation works.
	const [ currentTab, setCurrentTab ] = useState( getInitialTabName );
	const [ isEnabled, setIsEnabled ] = useState( getInitialEnabled );

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

	const handleEnabledChange = ( nextEnabled ) => {
		setIsEnabled( nextEnabled );
		// On disable, snap back to Overview so a future re-enable doesn't drop
		// the merchant on a hidden tab.
		if ( ! nextEnabled ) {
			setCurrentTab( 'overview' );
			if ( typeof window !== 'undefined' ) {
				window.location.hash = 'overview';
			}
		}
	};

	// Pre-enable: hide the tab nav entirely and render only the Overview tab's
	// PreEnableHero. The hero IS the page in this state.
	if ( ! isEnabled ) {
		return (
			<div className="wcpay-wsn-hub">
				<PageHeader />
				<div
					style={ {
						padding: `${ spacing.s4 } ${ spacing.s3 } 0`,
						background: colors.surface,
					} }
				>
					<OverviewTab
						isEnabled={ false }
						onEnabledChange={ handleEnabledChange }
					/>
				</div>
			</div>
		);
	}

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
					// Wrap tab content in TabErrorBoundary so an uncaught
					// throw inside any single tab can't blank the entire hub.
					<TabErrorBoundary>
						<div
							style={ {
								padding: `${ spacing.s4 } ${ spacing.s3 } 0`,
								background: colors.surface,
							} }
						>
							{ tab.name === 'overview' && (
								<OverviewTab
									isEnabled={ true }
									onEnabledChange={ handleEnabledChange }
								/>
							) }
							{ tab.name === 'visibility' && <VisibilityTab /> }
							{ tab.name === 'profile' && <ProfileTab /> }
						</div>
					</TabErrorBoundary>
				) }
			</TabPanel>
		</div>
	);
};

export default WsnHubApp;
