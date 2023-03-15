/**
 * External dependencies
 */
import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Card } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { BannerBody, NewPill, BannerActions } from './components';
import './style.scss';

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
		( null === settings.remindMeAt && ! settings.dontShowAgain ) ||
		( Date.now() > settings.remindMeAt && ! settings.dontShowAgain )
			? true
			: false;

	const setReminder = () => {
		const DAY_IN_MS = 24 * 60 * 60 * 1000;
		const nowTimestamp = Date.now();
		setSettings( ( prevSettings ) => {
			return {
				...prevSettings,
				remindMeCount: prevSettings.remindMeCount + 1,
				remindMeAt: nowTimestamp + DAY_IN_MS,
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

	if ( ! wcpaySettings.isFraudProtectionSettingsEnabled || ! showBanner ) {
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
