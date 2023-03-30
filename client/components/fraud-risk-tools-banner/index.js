/**
 * External dependencies
 */
import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Card } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { registerPlugin } from '@wordpress/plugins';
import {
	OPTIONS_STORE_NAME,
	ONBOARDING_STORE_NAME,
	getVisibleTasks,
} from '@woocommerce/data';

/**
 * Internal dependencies
 */
import { BannerBody, NewPill, BannerActions } from './components';
import './style.scss';
import { TIME } from '../../constants';

const FRTDiscoverabilityBanner = () => {
	const { frtDiscoverBannerSettings } = wcpaySettings;
	const { updateOptions } = useDispatch( 'wc/admin/options' );
	const [ settings, setSettings ] = useState( () => {
		try {
			return JSON.parse( frtDiscoverBannerSettings );
		} catch ( e ) {
			return { remindMeCount: 0, remindMeAt: null, dontShowAgain: false };
		}
	} );

	const showBanner =
		! settings.dontShowAgain &&
		( null === settings.remindMeAt || Date.now() > settings.remindMeAt );

	const setReminder = () => {
		const nowTimestamp = Date.now();
		setSettings( ( prevSettings ) => {
			return {
				...prevSettings,
				remindMeCount: prevSettings.remindMeCount + 1,
				remindMeAt: nowTimestamp + 3 * TIME.DAY_IN_MS,
			};
		} );
	};

	const setDontShowAgain = () => {
		setSettings( ( prevSettings ) => {
			return {
				...prevSettings,
				dontShowAgain: true,
			};
		} );
	};

	useEffect( () => {
		const stringifiedSettings = JSON.stringify( settings );

		updateOptions( {
			wcpay_frt_discover_banner_settings: stringifiedSettings,
		} );

		window.wcpaySettings.frtDiscoverBannerSettings = stringifiedSettings;
	}, [ frtDiscoverBannerSettings, settings, updateOptions ] );

	const handleRemindOnClick = () => {
		setReminder();
	};

	const handleDontShowAgainOnClick = () => {
		setDontShowAgain();
	};

	if ( ! showBanner ) {
		return null;
	}

	return (
		<>
			{ showBanner && (
				<Card>
					<div className="discoverability-card">
						<NewPill />
						<h3 className="discoverability-card__header">
							{ __(
								'Enhanced fraud protection for your store',
								'woocommerce-payments'
							) }
						</h3>
						<BannerBody />
						<BannerActions
							remindMeCount={ settings.remindMeCount }
							handleRemindOnClick={ handleRemindOnClick }
							handleDontShowAgainOnClick={
								handleDontShowAgainOnClick
							}
						/>
					</div>
				</Card>
			) }
		</>
	);
};

export default FRTDiscoverabilityBanner;

const FRTDiscoverabilityBannerFill = () => {
	const ADMIN_INSTALL_TIMESTAMP_OPTION_NAME =
		'woocommerce_admin_install_timestamp';
	const ONBOARDING_TASKLIST_ID = 'setup';
	const DAY_IN_SECONDS = 24 * 60 * 60;

	const getStoreAgeInDays = useSelect( ( select ) => {
		const {
			getOption,
			hasFinishedResolution: hasFinishedOptionResolution,
		} = select( OPTIONS_STORE_NAME );

		// Get admin install timestamp.
		const adminInstallTimestamp =
			getOption( ADMIN_INSTALL_TIMESTAMP_OPTION_NAME ) || 0;
		// Calculate store age in days.
		const storeAgeInDays = Math.floor(
			( Math.floor( Date.now() / 1000 ) - adminInstallTimestamp ) /
				DAY_IN_SECONDS
		);

		const isLoading = ! hasFinishedOptionResolution( 'getOption', [
			ADMIN_INSTALL_TIMESTAMP_OPTION_NAME,
		] );

		return {
			storeAgeInDays,
			isLoading,
		};
	} );

	const getTaskCompletionStatus = useSelect( ( select ) => {
		const {
			getTaskList,
			hasFinishedResolution: hasFinishedOnboardingResolution,
		} = select( ONBOARDING_STORE_NAME );

		// Get task list.
		const taskList = getTaskList( ONBOARDING_TASKLIST_ID );
		const visibleTasks = getVisibleTasks( taskList?.tasks || [] );
		const isLoading = ! hasFinishedOnboardingResolution( 'getTaskList', [
			ONBOARDING_TASKLIST_ID,
		] );

		// Tasklist completion conditions: Tasklist is hidden or all tasks inside are marked as completed.
		const allTasksAreCompleted =
			taskList?.isHidden ||
			0 === visibleTasks?.filter( ( task ) => ! task.isComplete ).length;

		return {
			allTasksAreCompleted,
			isLoading,
		};
	} );

	// Should only show the banner when all tasks are completed, or 30 days after the installation.
	const shouldShowBanner =
		! getTaskCompletionStatus.isLoading &&
		! getStoreAgeInDays.isLoading &&
		( getTaskCompletionStatus.getCompletedAllTasks ||
			30 < getStoreAgeInDays.storeAgeInDays );

	// Important: Use Fill from WC core, not the one within WCPay.
	const Fill = window.wp.components.Fill;

	return (
		shouldShowBanner && (
			<Fill name="woocommerce_homescreen_experimental_header_banner_item">
				<FRTDiscoverabilityBanner />
			</Fill>
		)
	);
};

registerPlugin( 'wc-payments-homescreen-fraud-protection-slotfill-banner', {
	render: () => {
		return <FRTDiscoverabilityBannerFill />;
	},
	scope: 'woocommerce-admin',
} );
