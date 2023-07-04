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

const FRTDiscoverabilityBanner: React.FC = () => {
	const { frtDiscoverBannerDismissed } = wcpaySettings;
	const { updateOptions } = useDispatch( 'wc/admin/options' );
	const [ setting, setSetting ] = useState< boolean >( () => {
		return frtDiscoverBannerDismissed;
	} );

	const showBanner = ! setting;

	const setDontShowAgain = () => {
		setSetting( true );
	};

	useEffect( () => {
		wcpayTracks.recordEvent( 'wcpay_fraud_protection_banner_rendered', {} );

		updateOptions( {
			wcpay_frt_discover_banner_dismissed: setting,
		} );

		wcpaySettings.frtDiscoverBannerDismissed = setting;
	}, [ frtDiscoverBannerDismissed, setting, updateOptions ] );

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
