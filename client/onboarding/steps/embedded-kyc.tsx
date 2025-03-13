/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';

/**
 * Internal dependencies
 */
import StripeSpinner from 'wcpay/components/stripe-spinner';
import { useOnboardingContext } from 'wcpay/onboarding/context';
import { finalizeOnboarding, isPoEligible } from 'wcpay/onboarding/utils';
import { getConnectUrl, getOverviewUrl } from 'wcpay/utils';
import { trackEmbeddedStepChange } from 'wcpay/onboarding/tracking';
import { EmbeddedAccountOnboarding } from 'wcpay/embedded-components';

interface Props {
	continueKyc?: boolean;
	collectPayoutRequirements?: boolean;
}

const EmbeddedKyc: React.FC< Props > = ( {
	continueKyc = false,
	collectPayoutRequirements = false,
} ) => {
	const { data } = useOnboardingContext();
	const [ finalizingAccount, setFinalizingAccount ] = useState( false );
	const [ isEligible, setIsEligible ] = useState< boolean | null >( null );

	// Fetch whether the account is eligible for progressive onboarding
	useEffect( () => {
		const checkEligibility = async () => {
			const eligibility = await isPoEligible( data );
			setIsEligible( eligibility );
		};

		if ( ! continueKyc ) {
			checkEligibility();
		} else {
			setIsEligible( false );
		}
	}, [ continueKyc, data ] );

	const handleStepChange = ( step: string ) => {
		trackEmbeddedStepChange( step );
	};

	const handleOnExit = async () => {
		const urlParams = new URLSearchParams( window.location.search );
		const urlSource =
			urlParams.get( 'source' )?.replace( /[^\w-]+/g, '' ) || 'unknown';

		setFinalizingAccount( true );

		try {
			const response = await finalizeOnboarding( urlSource );
			if ( response.success ) {
				window.location.href = getOverviewUrl(
					{
						...response.params,
						'wcpay-connection-success': '1',
					},
					'WCPAY_ONBOARDING_WIZARD'
				);
			} else {
				window.location.href = getConnectUrl(
					{
						...response.params,
						'wcpay-connection-error': '1',
					},
					'WCPAY_ONBOARDING_WIZARD'
				);
			}
		} catch ( error ) {
			window.location.href = getConnectUrl(
				{
					'wcpay-connection-error': '1',
					source: urlSource,
				},
				'WCPAY_ONBOARDING_WIZARD'
			);
		}
	};

	return (
		<>
			{ ! finalizingAccount && isEligible !== null && (
				<EmbeddedAccountOnboarding
					onExit={ handleOnExit }
					onStepChange={ handleStepChange }
					isPoEligible={ isEligible }
					onboardingData={ data }
					collectPayoutRequirements={ collectPayoutRequirements }
				/>
			) }
			{ finalizingAccount && (
				<div className="embedded-kyc-loader-wrapper">
					<StripeSpinner />
				</div>
			) }
		</>
	);
};

export default EmbeddedKyc;
