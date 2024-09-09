/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { useOnboardingContext } from '../context';
import { PoEligibleData, PoEligibleResult } from '../types';
import { fromDotNotation } from '../utils';
import { trackRedirected, useTrackAbandoned } from '../tracking';
import LoadBar from 'components/load-bar';
import strings from '../strings';

interface Props {
	name: string;
}

const LoadingStep: React.FC< Props > = () => {
	const { data } = useOnboardingContext();

	const { removeTrackListener } = useTrackAbandoned();

	const isEligibleForPo = async () => {
		if (
			! data.country ||
			! data.business_type ||
			! data.mcc ||
			! data.annual_revenue ||
			! data.go_live_timeframe
		) {
			return false;
		}
		const eligibilityDetails: PoEligibleData = {
			business: {
				country: data.country,
				type: data.business_type,
				mcc: data.mcc,
			},
			store: {
				annual_revenue: data.annual_revenue,
				go_live_timeframe: data.go_live_timeframe,
			},
		};
		const eligibleResult = await apiFetch< PoEligibleResult >( {
			path: '/wc/v3/payments/onboarding/router/po_eligible',
			method: 'POST',
			data: eligibilityDetails,
		} );

		return 'eligible' === eligibleResult.result;
	};

	const handleComplete = async () => {
		const { connectUrl } = wcpaySettings;

		let isPoEligible;
		try {
			isPoEligible = await isEligibleForPo();
		} catch ( error ) {
			// fall back to full KYC scenario.
			// TODO maybe log these errors in future, e.g. with tracks.
			isPoEligible = false;
		}

		trackRedirected( isPoEligible );
		removeTrackListener();

		const urlParams = new URLSearchParams( window.location.search );

		window.location.href = addQueryArgs( connectUrl, {
			self_assessment: fromDotNotation( data ),
			progressive: isPoEligible,
			source:
				urlParams.get( 'source' )?.replace( /[^\w-]+/g, '' ) ||
				'unknown',
			from: 'WCPAY_ONBOARDING_WIZARD',
		} );
	};

	useEffect( () => {
		handleComplete();
		// We only want to run this once, so we disable the exhaustive deps rule.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return (
		<div className="loading-step">
			<h1 className="stepper__heading">
				{ strings.steps.loading.heading }
			</h1>
			<LoadBar />
			<h2 className="stepper__subheading">
				{ strings.steps.loading.subheading }
			</h2>
		</div>
	);
};

export default LoadingStep;
