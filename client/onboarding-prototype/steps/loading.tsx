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
import { EligibleData, EligibleResult } from '../types';
import { fromDotNotation } from '../utils';

const Loading: React.FC = () => {
	const { data } = useOnboardingContext();

	const isEligibleForPo = async () => {
		if (
			! data.country ||
			! data.business_type ||
			! data.annual_revenue ||
			! data.go_live_timeframe
		) {
			return false;
		}
		const businessDetails: EligibleData = {
			business: {
				country: data.country,
				type: data.business_type,
				mcc: 'computers_peripherals_and_software', // TODO GH-4853 add MCC from onboarding form
				annual_revenue: data.annual_revenue,
				go_live_timeframe: data.go_live_timeframe,
			},
		};
		const eligibleResult = await apiFetch< EligibleResult >( {
			path: '/wc/v3/payments/onboarding/router/po_eligible',
			method: 'POST',
			data: businessDetails,
		} );

		return 'eligible' === eligibleResult.result;
	};

	const handleComplete = async () => {
		const { connectUrl } = wcpaySettings;
		// TODO GH-5476 prefill the data for full KYC with addQueryArgs( connectUrl, {prefill: fromDotNotation( data ),} )
		// that needs server tweaks first.
		let isEligible;
		try {
			isEligible = await isEligibleForPo();
		} catch ( error ) {
			// fall back to full KYC scenario.
			// TODO maybe log these errors in future, e.g. with tracks.
			isEligible = false;
		}
		const resultUrl = isEligible
			? addQueryArgs( connectUrl, {
					progressive: fromDotNotation( data ),
			  } )
			: connectUrl;
		window.location.href = resultUrl;
	};

	useEffect( () => {
		handleComplete();
		// We only want to run this once, so we disable the exhaustive deps rule.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	// TODO implement in GH-4744 (Create or extend components needed for PO)
	return <></>;
};

export default Loading;
