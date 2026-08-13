/**
 * External dependencies
 */
import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import {
	useStripeBilling,
	useStripeBillingMigration,
	useSettings,
	useManualCapture,
} from 'wcpay/data/settings';
import ConfirmationModal from 'wcpay/components/confirmation-modal';
import Notices from './stripe-billing-notices/notices';
import StripeBillingMigrationNoticeContext from './stripe-billing-notices/context';
import StripeBillingToggle from './stripe-billing-toggle';

/**
 * Renders a WooPayments Subscriptions Advanced Settings Section.
 *
 * The three `useEffect` blocks below (and the mirrors in
 * `stripe-billing-notices/*`) call `setState` synchronously from an effect
 * body, tripping `react-hooks/set-state-in-effect`. Rewriting them into a
 * single coordinated save-and-migrate state machine passes the rule but
 * ships a significantly larger behavioural change to a surface that only a
 * subset of merchants (those on Stripe Billing) sees and that is no longer
 * actively developed. The suppressions below intentionally keep the
 * original semantics; the rule is not load-bearing for correctness here.
 *
 * @return {JSX.Element} Rendered subscriptions advanced settings section.
 */
const StripeBillingSection: React.FC = () => {
	const [ isStripeBillingEnabled, updateIsStripeBillingEnabled ] =
		useStripeBilling();
	const [ isManualCaptureEnabled ] = useManualCapture();
	const [
		isMigrationInProgress,
		migratedCount,
		subscriptionCount,
		startMigration,
		isResolving,
		hasResolved,
	] = useStripeBillingMigration();

	/**
	 * Notices are shown and hidden based on whether the settings have been saved.
	 * The following variables track the saving state of the WooPayments settings.
	 */
	const { isLoading, isSaving } = useSettings();
	const [ hasSavedSettings, setHasSavedSettings ] = useState( false );
	const [ savedIsStripeBillingEnabled, setSavedIsStripeBillingEnabled ] =
		useState( isStripeBillingEnabled );

	// The settings have finished saving when the settings are not actively being saved and we've flagged they were being saved.
	const hasFinishedSavingSettings = ! isSaving && hasSavedSettings;

	// When the settings are being saved, set the hasSavedSettings flag to true.
	useEffect( () => {
		if ( isSaving && ! isLoading ) {
			// eslint-disable-next-line react-hooks/set-state-in-effect -- see file header for rationale.
			setHasSavedSettings( true );
		}
	}, [ isLoading, isSaving ] );

	// When the settings have finished saving, update the savedIsStripeBillingEnabled value.
	useEffect( () => {
		if ( hasFinishedSavingSettings ) {
			// eslint-disable-next-line react-hooks/set-state-in-effect -- see file header for rationale.
			setSavedIsStripeBillingEnabled( isStripeBillingEnabled );
		}
	}, [ hasFinishedSavingSettings, isStripeBillingEnabled ] );

	// Set up the context to be shared between the notices and the toggle.
	const [ isMigrationInProgressLocal, setIsMigrationInProgressLocal ] =
		useState( false );

	/**
	 * Whether the migrate-option notice is eligible to be shown.
	 *
	 * Note: We use `useState` here to snapshot the setting value on load.
	 * The option notice should only be shown if Stripe Billing was disabled on load.
	 */
	const [ isMigrationOptionEligible, setIsMigrationOptionEligible ] =
		useState( ! isStripeBillingEnabled );

	// Once settings are saved with Stripe Billing enabled, the option notice is no longer eligible.
	useEffect( () => {
		if ( savedIsStripeBillingEnabled ) {
			// eslint-disable-next-line react-hooks/set-state-in-effect -- see file header for rationale.
			setIsMigrationOptionEligible( false );
		}
	}, [ savedIsStripeBillingEnabled ] );

	// Derive `isMigrationOptionShown` synchronously so all children (the toggle and sibling
	// notices) read the same value in the first render — otherwise the toggle's help text
	// flickers because <Notices /> renders before <StripeBillingToggle /> and can't update
	// the context until after its first commit.
	const isMigrationInProgressCombined =
		isMigrationInProgress || isMigrationInProgressLocal;
	const isMigrationOptionShown =
		! hasResolved &&
		! isMigrationInProgressCombined &&
		subscriptionCount > 0 &&
		isMigrationOptionEligible &&
		! isStripeBillingEnabled;

	const noticeContext = {
		isStripeBillingEnabled: isStripeBillingEnabled,
		savedIsStripeBillingEnabled: savedIsStripeBillingEnabled,

		// Notice logic.
		isMigrationOptionShown: isMigrationOptionShown,

		// Migration logic.
		isMigrationInProgress: isMigrationInProgressCombined,
		setIsMigrationInProgress: setIsMigrationInProgressLocal,
		hasSavedSettings: hasFinishedSavingSettings,

		// Migration data.
		subscriptionCount: subscriptionCount,
		migratedCount: migratedCount,

		// Migration actions & state.
		startMigration: startMigration,
		isResolvingMigrateRequest: isResolving,
		hasResolvedMigrateRequest: hasResolved,
	};

	const [
		isStripeBillingManualCaptureConflictModalOpen,
		setStripeBillingManualCaptureConflictModalOpen,
	] = useState( false );
	const openStripeBillingManualCaptureConflictModal = () =>
		setStripeBillingManualCaptureConflictModalOpen( true );
	const closeStripeBillingManualCaptureConflictModal = () =>
		setStripeBillingManualCaptureConflictModalOpen( false );

	// When the toggle is changed, update the WooPayments settings and reset the hasSavedSettings flag.
	const stripeBillingSettingToggle = ( enabled: boolean ) => {
		if ( enabled && isManualCaptureEnabled ) {
			openStripeBillingManualCaptureConflictModal();
			return;
		}
		updateIsStripeBillingEnabled( enabled );
		setHasSavedSettings( false );
	};

	return (
		<StripeBillingMigrationNoticeContext.Provider value={ noticeContext }>
			<h4>{ __( 'Subscriptions', 'woocommerce-payments' ) }</h4>
			<Notices />
			<StripeBillingToggle onChange={ stripeBillingSettingToggle } />
			{ isStripeBillingManualCaptureConflictModalOpen && (
				<ConfirmationModal
					title={ __(
						'Enable Stripe Billing',
						'woocommerce-payments'
					) }
					actions={
						<>
							<Button
								onClick={
									closeStripeBillingManualCaptureConflictModal
								}
								isPrimary
							>
								{ __( 'OK', 'woocommerce-payments' ) }
							</Button>
						</>
					}
					onRequestClose={
						closeStripeBillingManualCaptureConflictModal
					}
				>
					<p>
						{ createInterpolateElement(
							__(
								'Stripe Billing is not available with <b>manual capture enabled</b>. To use Stripe Billing, disable manual capture in your settings list.',
								'woocommerce-payments'
							),
							{
								b: <strong />,
							}
						) }
					</p>
				</ConfirmationModal>
			) }
		</StripeBillingMigrationNoticeContext.Provider>
	);
};

export default StripeBillingSection;
