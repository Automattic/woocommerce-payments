/**
 * External dependencies
 */
import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */

import { useSettings } from 'wcpay/data/settings';
import Notices from './stripe-billing-notices/notices';
import StripeBillingMigrationNoticeContext from './stripe-billing-notices/context';
import StripeBillingToggle from './stripe-billing-toggle';
import { StripeBillingHook, StripeBillingMigrationHook } from './interfaces';
import { useDispatch, useSelect } from '@wordpress/data';
import { STORE_NAME } from 'wcpay/data/constants';

const useStripeBilling = () => {
	const { updateIsStripeBillingEnabled } = useDispatch( STORE_NAME );

	const isStripeBillingEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsStripeBillingEnabled()
	);

	return [ isStripeBillingEnabled, updateIsStripeBillingEnabled ];
};

const useStripeBillingMigration = () => {
	const { submitStripeBillingSubscriptionMigration } = useDispatch(
		STORE_NAME
	);

	return useSelect( ( select ) => {
		const { getStripeBillingSubscriptionCount } = select( STORE_NAME );
		const { getIsStripeBillingMigrationInProgress } = select( STORE_NAME );
		const { isResolving } = select( STORE_NAME );
		const hasResolved = select( STORE_NAME ).hasFinishedResolution(
			'scheduleStripeBillingMigration'
		);
		const { getStripeBillingMigratedCount } = select( STORE_NAME );

		return [
			getIsStripeBillingMigrationInProgress(),
			getStripeBillingMigratedCount(),
			getStripeBillingSubscriptionCount(),
			submitStripeBillingSubscriptionMigration,
			isResolving( 'scheduleStripeBillingMigration' ),
			hasResolved,
		];
	}, [] );
};

/**
 * Renders a WooPayments Subscriptions Advanced Settings Section.
 *
 * @return {JSX.Element} Rendered subscriptions advanced settings section.
 */
const StripeBillingSection: React.FC = () => {
	const [
		isStripeBillingEnabled,
		updateIsStripeBillingEnabled,
	] = useStripeBilling() as StripeBillingHook;
	const [
		isMigrationInProgress,
		migratedCount,
		subscriptionCount,
		startMigration,
		isResolving,
		hasResolved,
	] = useStripeBillingMigration() as StripeBillingMigrationHook;

	/**
	 * Notices are shown and hidden based on whether the settings have been saved.
	 * The following variables track the saving state of the WooPayments settings.
	 */
	const { isLoading, isSaving } = useSettings();
	const [ hasSavedSettings, setHasSavedSettings ] = useState( false );
	const [
		savedIsStripeBillingEnabled,
		setSavedIsStripeBillingEnabled,
	] = useState( isStripeBillingEnabled );

	// The settings have finished saving when the settings are not actively being saved and we've flagged they were being saved.
	const hasFinishedSavingSettings = ! isSaving && hasSavedSettings;

	// When the settings are being saved, set the hasSavedSettings flag to true.
	useEffect( () => {
		if ( isSaving && ! isLoading ) {
			setHasSavedSettings( true );
		}
	}, [ isLoading, isSaving ] );

	// When the settings have finished saving, update the savedIsStripeBillingEnabled value.
	useEffect( () => {
		if ( hasFinishedSavingSettings ) {
			setSavedIsStripeBillingEnabled( isStripeBillingEnabled );
		}
	}, [ hasFinishedSavingSettings, isStripeBillingEnabled ] );

	// Set up the context to be shared between the notices and the toggle.
	const [ isMigrationInProgressShown ] = useState( false );
	const [ isMigrationOptionShown ] = useState( false );

	const noticeContext = {
		isStripeBillingEnabled: isStripeBillingEnabled,
		savedIsStripeBillingEnabled: savedIsStripeBillingEnabled,

		// Notice logic.
		isMigrationOptionShown: isMigrationOptionShown,
		isMigrationInProgressShown: isMigrationInProgressShown,

		// Migration logic.
		isMigrationInProgress: isMigrationInProgress,
		hasSavedSettings: hasFinishedSavingSettings,

		// Migration data.
		subscriptionCount: subscriptionCount,
		migratedCount: migratedCount,

		// Migration actions & state.
		startMigration: startMigration,
		isResolvingMigrateRequest: isResolving,
		hasResolvedMigrateRequest: hasResolved,
	};

	// When the toggle is changed, update the WooPayments settings and reset the hasSavedSettings flag.
	const stripeBillingSettingToggle = ( enabled: boolean ) => {
		updateIsStripeBillingEnabled( enabled );
		setHasSavedSettings( false );
	};

	return (
		<StripeBillingMigrationNoticeContext.Provider value={ noticeContext }>
			<h4>{ __( 'Subscriptions', 'woocommerce-payments' ) }</h4>
			<Notices />
			<StripeBillingToggle onChange={ stripeBillingSettingToggle } />
		</StripeBillingMigrationNoticeContext.Provider>
	);
};

export default StripeBillingSection;
