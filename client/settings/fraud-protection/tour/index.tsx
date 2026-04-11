/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { TourKit } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import { useSettings } from 'wcpay/data';
import { steps } from './steps';
import { recordEvent } from 'tracks';
import { saveOption } from 'wcpay/data/settings/actions';

const options = {
	effects: {
		arrowIndicator: true,
		spotlight: { styles: { padding: 8 } },
		autoScroll: {
			behavior: 'smooth',
			block: 'nearest',
		},
	},
	popperModifiers: [
		{
			name: 'offset',
			options: {
				offset: [ 0, 20 ],
			},
		},
	],
};

const FraudProtectionTour: React.FC = () => {
	const { isWelcomeTourDismissed } = wcpaySettings.fraudProtection;

	const { isLoading } = useSettings();
	const [ showTour, setShowTour ] = useState( false );

	useEffect( () => {
		const reference = document.getElementById( 'fp-settings' );
		if ( isWelcomeTourDismissed || isLoading || ! reference ) return;

		const observer = new IntersectionObserver(
			( [ entry ] ) => {
				if ( entry.isIntersecting ) {
					setShowTour( true );
					observer.disconnect();
				}
			},
			{ threshold: 1 }
		);

		observer.observe( reference );
		return () => observer.disconnect();
	}, [ isLoading, isWelcomeTourDismissed ] );

	const handleTourEnd = (
		stepList: any[],
		currentIndex: number,
		element: string
	) => {
		saveOption( 'wcpay_fraud_protection_welcome_tour_dismissed', true );
		wcpaySettings.fraudProtection.isWelcomeTourDismissed = true;

		setShowTour( false );

		if ( 'done-btn' === element ) {
			recordEvent( 'wcpay_fraud_protection_tour_clicked_through' );
		} else {
			recordEvent( 'wcpay_fraud_protection_tour_abandoned' );
		}
	};

	if ( ! showTour ) return null;

	return (
		<TourKit
			config={ {
				steps,
				options,
				closeHandler: handleTourEnd,
			} }
		/>
	);
};

export default FraudProtectionTour;
