/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { useOnboardingContext } from '../context';
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

	const handleComplete = async () => {
		const { connectUrl } = wcpaySettings;

		trackRedirected();
		removeTrackListener();

		const urlParams = new URLSearchParams( window.location.search );

		window.location.href = addQueryArgs( connectUrl, {
			self_assessment: fromDotNotation( data ),
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
		<div className="loading-step centered">
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
