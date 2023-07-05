/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Card } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { BannerBody, NewPill, BannerActions } from './components';
import './style.scss';
import wcpayTracks from 'tracks';

interface BannerSettings {
	dontShowAgain: boolean;
}

const FRTDiscoverabilityBanner: React.FC = () => {
	const { frtDiscoverBannerSettings } = wcpaySettings;
	const { updateOptions } = useDispatch( 'wc/admin/options' );
	const [ settings, setSettings ] = useState< BannerSettings >( () => {
		try {
			return JSON.parse( frtDiscoverBannerSettings );
		} catch ( e ) {
			return { dontShowAgain: false };
		}
	} );

	const showBanner = ! settings.dontShowAgain;

	const setDontShowAgain = () => {
		setSettings( { dontShowAgain: true } );
	};

	useEffect( () => {
		wcpayTracks.recordEvent( 'wcpay_fraud_protection_banner_rendered', {} );

		const stringifiedSettings = JSON.stringify( settings );

		updateOptions( {
			wcpay_frt_discover_banner_settings: stringifiedSettings,
		} );

		wcpaySettings.frtDiscoverBannerSettings = stringifiedSettings;
	}, [ frtDiscoverBannerSettings, settings, updateOptions ] );

	const handleDontShowAgainOnClick = () => {
		setDontShowAgain();
	};

	if ( ! showBanner ) {
		return null;
	}

	return (
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
					handleDontShowAgainOnClick={ handleDontShowAgainOnClick }
				/>
			</div>
		</Card>
	);
};

export default FRTDiscoverabilityBanner;
