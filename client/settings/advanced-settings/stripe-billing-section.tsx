/**
 * External dependencies
 */
import React, { useEffect, useRef, useState } from 'react';
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
 * The save-and-migrate state machine is owned here and exposed to the notices
 * and toggle via `StripeBillingMigrationNoticeContext`:
 *
 *   - `wasSavingRef` remembers the previous `isSaving` value so we can detect
 *     the `isSaving: true → false` transition from render (no state, no
 *     effect-driven cascade re-renders).
 *   - `savedIsStripeBillingEnabledRef` snapshots the Stripe Billing value at
 *     each save boundary. Initialised from the mount value so the notices have
 *     a valid snapshot before the first save.
 *   - `hasEverSavedEnabledRef` latches true once Stripe Billing has ever been
 *     saved-enabled (or was enabled on mount). It locks the migrate-option
 *     notice off — once Stripe Billing has been enabled, the "migrate now"
 *     prompt is no longer offered.
 *   - `hasCompletedSaveRef` latches true after the first completed save cycle.
 *     Notices use this to distinguish "server-reported migration on mount"
 *     from "user just saved with Stripe Billing disabled".
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

	const { isSaving } = useSettings();

	// Track the previous `isSaving` value so we can detect the "save finished"
	// transition without a `setState`-in-effect cascade.
	const wasSavingRef = useRef( false );

	// Latched snapshots of Stripe Billing state at save boundaries. Initialised
	// from the mount value so the notices have a valid snapshot before the
	// first save cycle.
	const savedIsStripeBillingEnabledRef = useRef( isStripeBillingEnabled );
	const hasEverSavedEnabledRef = useRef( isStripeBillingEnabled );
	const hasCompletedSaveRef = useRef( false );

	const hasFinishedSavingSettings = wasSavingRef.current && ! isSaving;

	// Render-time snapshot update: on the render where we detect the save
	// finished, capture the new Stripe Billing value into the refs. Refs never
	// trigger re-renders, so this is safe and does not fire the
	// set-state-in-effect rule.
	if ( hasFinishedSavingSettings ) {
		savedIsStripeBillingEnabledRef.current = isStripeBillingEnabled;
		hasCompletedSaveRef.current = true;
		if ( isStripeBillingEnabled ) {
			hasEverSavedEnabledRef.current = true;
		}
	}

	useEffect( () => {
		wasSavingRef.current = isSaving;
	}, [ isSaving ] );

	// Local mirror for a migration triggered from the "Begin migration" button.
	const [ isMigrationInProgressLocal, setIsMigrationInProgressLocal ] =
		useState( false );

	const isMigrationInProgressCombined =
		isMigrationInProgress || isMigrationInProgressLocal;

	// The migrate-option notice is only eligible when Stripe Billing has never
	// been saved-enabled (and was not enabled on mount). Both conditions
	// collapse into `hasEverSavedEnabledRef` because it is initialised from
	// the mount value.
	const isMigrationOptionEligible = ! hasEverSavedEnabledRef.current;

	const isMigrationOptionShown =
		! hasResolved &&
		! isMigrationInProgressCombined &&
		subscriptionCount > 0 &&
		isMigrationOptionEligible &&
		! isStripeBillingEnabled;

	const noticeContext = {
		isStripeBillingEnabled: isStripeBillingEnabled,
		savedIsStripeBillingEnabled: savedIsStripeBillingEnabledRef.current,

		// Notice logic.
		isMigrationOptionShown: isMigrationOptionShown,

		// Migration logic.
		isMigrationInProgress: isMigrationInProgressCombined,
		setIsMigrationInProgress: setIsMigrationInProgressLocal,
		hasCompletedSave: hasCompletedSaveRef.current,

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

	const stripeBillingSettingToggle = ( enabled: boolean ) => {
		if ( enabled && isManualCaptureEnabled ) {
			openStripeBillingManualCaptureConflictModal();
			return;
		}
		updateIsStripeBillingEnabled( enabled );
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
