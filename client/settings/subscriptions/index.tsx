/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Card, CheckboxControl, ExternalLink } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './style.scss';
import CardBody from '../card-body';
import { useStripeBilling, useSettings } from 'wcpay/data';
import MigrationInProgressNotice from './migration-progress-notice';
import MigrateOptionNotice from './migrate-option-notice';
import MigrateAutomaticallyNotice from './migrate-automatically-notice';
import MigrationCompletedNotice from './migrate-completed-notice';
import Loadable from 'wcpay/components/loadable';

/**
 * Renders a WooPayments subscriptions settings card.
 *
 * @return {JSX.Element} Rendered subscriptions element.
 */
const Subscriptions: React.FC = () => {
	const [
		isStripeBillingEnabled,
		isMigratingStripeBilling,
		stripeBillingSubscriptionCount,
		updateIsStripeBillingEnabled,
		submitStripeBillingSubscriptionMigration,
		isProcessingMigrateRequest,
		hasScheduledMigration,
		completedMigrationCount,
	] = useStripeBilling() as [
		boolean,
		boolean,
		number,
		( value: boolean ) => void,
		() => void,
		boolean,
		boolean,
		number
	];

	/**
	 * Notices are shown and hidden based on whether the settings have been saved.
	 * The following variables track the saving state of the WooPayments settings.
	 */
	const { isLoading, isSaving } = useSettings();
	const [ hasSavedSettings, setHasSavedSettings ] = useState( false );

	// The settings have finished saving when the settings are not actively being saved and we've flagged they were being saved.
	const hasFinishedSavingSettings = ! isSaving && hasSavedSettings;

	// When the settings are being saved, set the hasSavedSettings flag to true.
	useEffect( () => {
		if ( isSaving && ! isLoading ) {
			setHasSavedSettings( true );
		}
	}, [ isLoading, isSaving ] );

	// Tracks whether the user has dismissed the migration in progress notice.
	const [ migrationInProgressNoticeDismissed, setDismissed ] = useState(
		false
	);

	// Tracks whether the user has started a migration.
	const [ hasStartedMigration, setHasStartedMigration ] = useState( false );

	// We only offer the migration option if Stripe Billing is not enabled at the time of page load.
	const [ offerMigrationOption, setOfferMigrationOption ] = useState(
		! isStripeBillingEnabled
	);

	/**
	 * The notice which contains the option to migrate to on-site billing is shown when:
	 *  - Stripe Billing is not enabled.
	 *  - A migration is not in progress.
	 *  - There are subscriptions using Stripe Billing.
	 *  - The user has not started a migration.
	 *  - We're offering the migrate option - ie Stripe Billing was not enabled at the time of page load.
	 */
	const displayMigrationOptionNotice =
		! isStripeBillingEnabled &&
		! isMigratingStripeBilling &&
		stripeBillingSubscriptionCount > 0 &&
		! hasStartedMigration &&
		offerMigrationOption;

	// Retract the offer to migrate when the settings have finished saving, and Stripe Billing is enabled.
	// If Stripe Billing is later disabled, automatic migration will occur.
	useEffect( () => {
		if (
			offerMigrationOption &&
			isStripeBillingEnabled &&
			hasFinishedSavingSettings
		) {
			setOfferMigrationOption( false );
		}
	}, [
		offerMigrationOption,
		isStripeBillingEnabled,
		hasFinishedSavingSettings,
	] );

	/**
	 * The notice which contains a warning about migrating to on-site billing is shown when:
	 *  - Stripe Billing is not enabled.
	 *  - Migration is not in progress.
	 *  - There are subscriptions using Stripe Billing.
	 *  - The notice containing the option is not shown.
	 *  - The user has not dismissed the in-progress notice.
	 */
	const displayMigrateAutomaticallyNotice =
		! isStripeBillingEnabled &&
		! isMigratingStripeBilling &&
		stripeBillingSubscriptionCount > 0 &&
		! displayMigrationOptionNotice &&
		! migrationInProgressNoticeDismissed;

	/**
	 * The notice which contains information about the migration progress is shown when:
	 *  - A migration is in progress or
	 *  - The user has requested a migration via the migration option notice.
	 */
	const [
		displayMigrationInProgressNotice,
		setDisplayMigrationInProgressNotice,
	] = useState( isMigratingStripeBilling || hasScheduledMigration );

	/**
	 * Show the migration is in progress notice when:
	 *  - The settings have finished saving.
	 *  - The warning about migrating automatically is being displayed.
	 *  - Stripe Billing is not enabled (saved).
	 *  - There are subscriptions using Stripe Billing.
	 */
	useEffect( () => {
		if (
			hasFinishedSavingSettings &&
			displayMigrateAutomaticallyNotice &&
			! isStripeBillingEnabled &&
			stripeBillingSubscriptionCount > 0
		) {
			setDisplayMigrationInProgressNotice( true );
		}
	}, [
		displayMigrateAutomaticallyNotice,
		hasFinishedSavingSettings,
		isStripeBillingEnabled,
		stripeBillingSubscriptionCount,
	] );

	// Hide the migration option notice when the user has started a migration.
	useEffect( () => {
		if ( hasScheduledMigration ) {
			setHasStartedMigration( true );
			setDisplayMigrationInProgressNotice( true );
		}
	}, [ hasScheduledMigration ] );

	/**
	 * The notice which contains information about a completed migration is shown when:
	 *  - A migration is not in progress.
	 *  - There are completed subscription migrated.
	 */
	const [
		displayCompletedMigrationNotice,
		setDisplayCompletedMigrationNotice,
	] = useState(
		! isMigratingStripeBilling &&
			completedMigrationCount > 0 &&
			stripeBillingSubscriptionCount === 0 &&
			! isStripeBillingEnabled
	);

	/**
	 * Handle the Stripe Billing setting checkbox click.
	 *
	 * @param {boolean} checked Whether the checkbox is checked.
	 */
	const handleCheckboxClick = ( checked: boolean ) => {
		updateIsStripeBillingEnabled( checked );
		setHasSavedSettings( false );
	};

	return (
		<Card className="subscriptions">
			<CardBody>
				<h4>
					{ __( 'Subscription billing', 'woocommerce-payments' ) }
				</h4>
				{ displayCompletedMigrationNotice &&
					! displayMigrationInProgressNotice &&
					! displayMigrationOptionNotice && (
						<MigrationCompletedNotice
							completedMigrationCount={ completedMigrationCount }
							onRemove={ () => {
								setDisplayCompletedMigrationNotice( false );
							} }
						/>
					) }
				{ displayMigrationOptionNotice && (
					<MigrateOptionNotice
						stripeBillingSubscriptionCount={
							stripeBillingSubscriptionCount
						}
						startMigration={ () => {
							submitStripeBillingSubscriptionMigration();
						} }
						isLoading={ isProcessingMigrateRequest }
					/>
				) }
				{ displayMigrationInProgressNotice && (
					<MigrationInProgressNotice
						stripeBillingSubscriptionCount={
							stripeBillingSubscriptionCount
						}
						onRemove={ () => {
							setDismissed( true );
							setDisplayMigrationInProgressNotice( false );
						} }
					/>
				) }
				{ displayMigrateAutomaticallyNotice &&
					! displayMigrationInProgressNotice &&
					! migrationInProgressNoticeDismissed && (
						<MigrateAutomaticallyNotice
							stripeBillingSubscriptionCount={
								stripeBillingSubscriptionCount
							}
						/>
					) }
				<CheckboxControl
					checked={ isStripeBillingEnabled }
					onChange={ handleCheckboxClick }
					label={ __(
						'Enable Stripe Billing for future subscriptions',
						'woocommerce-payments'
					) }
					help={ interpolateComponents( {
						mixedString: sprintf(
							displayMigrationOptionNotice
								? __(
										'Alternatively, you can enable this setting and future %s subscription purchases will also utilize' +
											' Stripe Billing for payment processing. Note: This feature supports card payments only and' +
											' may lack support for key subscription features.' +
											' {{learnMoreLink}}Learn more{{/learnMoreLink}}',
										'woocommerce-payments'
								  )
								: __(
										'By enabling this setting, future %s subscription purchases will utilize Stripe Billing for payment' +
											' processing. Note: This feature supports card payments only and may lack support for key' +
											' subscription features. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
										'woocommerce-payments'
								  ),
							'WooPayments'
						),
						components: {
							learnMoreLink: (
								// eslint-disable-next-line max-len
								<ExternalLink href="https://woocommerce.com/document/woopayments/built-in-subscriptions/comparison/#billing-engine" />
							),
						},
					} ) }
				/>
			</CardBody>
		</Card>
	);
};

export default Subscriptions;
