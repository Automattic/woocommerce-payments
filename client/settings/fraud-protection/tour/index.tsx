/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { useDispatch } from '@wordpress/data';
import { TourKit } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import { useSettings } from '../../../data';
import { steps } from './steps';
import wcpayTracks from 'tracks';

const FraudProtectionTour: React.FC = () => {
	const { isWelcomeTourDismissed } = wcpaySettings.fraudProtection;

	const { isLoading } = useSettings();
	const { updateOptions } = useDispatch( 'wc/admin/options' );
	const [ showTour, setShowTour ] = useState( false );

	useEffect( () => {
		if ( ! isLoading && ! isWelcomeTourDismissed ) {
			setShowTour( true );
		}
	}, [ isLoading, isWelcomeTourDismissed ] );

	const handleTourEnd = (
		stepList: any,
		currentIndex: any,
		element: any
	) => {
		updateOptions( {
			wcpay_fraud_protection_welcome_tour_dismissed: true,
		} );
		wcpaySettings.fraudProtection.isWelcomeTourDismissed = true;

		setShowTour( false );

		if ( 'done-btn' === element ) {
			wcpayTracks.recordEvent(
				'wcpay_fraud_protection_tour_clicked_through',
				{}
			);
		} else {
			wcpayTracks.recordEvent(
				'wcpay_fraud_protection_tour_abandoned',
				{}
			);
		}
	};

	const options = {
		effects: {
			overlay: true,
			autoScroll: {
				behavior: 'smooth',
			},
		},
	};

	if ( ! showTour ) return null;

	return (
		<TourKit
			config={ {
				steps,
				options,
				placement: 'top',
				closeHandler: handleTourEnd,
			} }
		/>
	);
};

export default FraudProtectionTour;
