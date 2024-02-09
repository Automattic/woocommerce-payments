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
import { recordEvent } from 'tracks';

const [ firstStep ] = steps;
const { desktop: firstStepId } = firstStep.referenceElements;

const options = {
	effects: {
		overlay: true,
		autoScroll: {
			behavior: 'smooth',
		},
	},
	popperModifiers: [
		{
			name: 'applyArrowHide',
			enabled: true,
			phase: 'write',
			fn( { state }: any ) {
				const { arrow, reference } = state.elements;

				if ( ! arrow ) return;

				if ( `#${ reference.id }` === firstStepId ) {
					arrow.setAttribute( 'data-hide', '' );
				} else {
					arrow.removeAttribute( 'data-hide' );
				}
			},
		},
	],
};

const FraudProtectionTour: React.FC = () => {
	const { isWelcomeTourDismissed } = wcpaySettings.fraudProtection;

	const searchParams = new URLSearchParams( window.location.search );
	const anchorParam = searchParams.get( 'anchor' );
	const isTourParam = '#fp-settings' === anchorParam;

	const { isLoading } = useSettings();
	const { updateOptions } = useDispatch( 'wc/admin/options' );
	const [ showTour, setShowTour ] = useState( false );

	useEffect( () => {
		if ( ! isLoading && ! isWelcomeTourDismissed && isTourParam ) {
			setShowTour( true );
		}
	}, [ isLoading, isWelcomeTourDismissed, isTourParam ] );

	const handleTourEnd = (
		stepList: any[],
		currentIndex: number,
		element: string
	) => {
		updateOptions( {
			wcpay_fraud_protection_welcome_tour_dismissed: true,
		} );
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
		<>
			<div id="fraud-protection-welcome-tour-first-step" />
			<TourKit
				config={ {
					steps,
					options,
					placement: 'top',
					closeHandler: handleTourEnd,
				} }
			/>
		</>
	);
};

export default FraudProtectionTour;
