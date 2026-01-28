/**
 * External dependencies
 */
import React, { useCallback, useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Spotlight from 'components/spotlight';
import { useReviewPromptState } from './hooks';
import { recordEvent } from 'wcpay/tracks';

const wordpressOrgReviewUrl =
	'https://wordpress.org/support/plugin/woocommerce-payments/reviews/#new-post';
const marketplaceReviewUrl =
	'https://woocommerce.com/products/woocommerce-payments/#reviews';

/**
 * Helper to record an event with base properties and optional additional properties.
 */
const recordPromptEvent = (
	eventName: string,
	baseProperties: Record< string, unknown >,
	additionalProperties?: Record< string, unknown >
) => {
	recordEvent( eventName, { ...baseProperties, ...additionalProperties } );
};

/**
 * Helper to calculate time-to-click properties.
 */
const getTimeToClickProps = (
	viewTimestamp: number | null
): Record< string, number > => {
	const timeToClickMs = viewTimestamp ? Date.now() - viewTimestamp : null;
	return timeToClickMs !== null ? { time_to_click_ms: timeToClickMs } : {};
};

/**
 * Helper to get base event properties per PRO2-35 telemetry requirements.
 */
const getBaseEventProperties = ( isAccountEligible: boolean | undefined ) => {
	return {
		prompt_id: 'phase0_payments_settings_001',
		extension: 'woopayments',
		location: 'payments_settings_top_level',
		trigger: 'none',
		flag_enabled: isAccountEligible,
		version: wcpaySettings?.version || 'unknown',
	};
};

const ReviewPrompt: React.FC = () => {
	const { dismissPrompt, setMaybeLater } = useReviewPromptState();

	const [ viewTimestamp, setViewTimestamp ] = useState< number | null >(
		null
	);

	// Eligibility is already checked server-side, so if this component renders, the prompt should show.
	const isAccountEligible =
		wcpaySettings?.accountStatus?.campaigns?.reviewPromptPhase0;

	const handleView = useCallback( () => {
		const timestamp = Date.now();
		setViewTimestamp( timestamp );
		recordPromptEvent(
			'payments_review_prompt_shown',
			getBaseEventProperties( isAccountEligible )
		);
	}, [ isAccountEligible ] );

	const handlePrimaryClick = useCallback( () => {
		// Determine destination based on connection state
		const isLive = wcpaySettings?.accountStatus?.isLive;
		const destination = isLive ? 'wordpress_org' : 'marketplace';
		const reviewUrl = isLive ? wordpressOrgReviewUrl : marketplaceReviewUrl;

		const baseProps = getBaseEventProperties( isAccountEligible );
		const eventProps = {
			action: 'write_review',
			destination,
			...getTimeToClickProps( viewTimestamp ),
		};

		recordPromptEvent(
			'payments_review_prompt_action',
			baseProps,
			eventProps
		);
		recordPromptEvent(
			'payments_review_destination_selected',
			baseProps,
			eventProps
		);

		window.open( reviewUrl, '_blank', 'noopener,noreferrer' );
		dismissPrompt();
	}, [ isAccountEligible, viewTimestamp, dismissPrompt ] );

	const handleSecondaryClick = useCallback( () => {
		recordPromptEvent(
			'payments_review_prompt_action',
			getBaseEventProperties( isAccountEligible ),
			{
				action: 'maybe_later',
				...getTimeToClickProps( viewTimestamp ),
			}
		);
		setMaybeLater();
	}, [ isAccountEligible, viewTimestamp, setMaybeLater ] );

	const handleDismiss = useCallback( () => {
		recordPromptEvent(
			'payments_review_prompt_action',
			getBaseEventProperties( isAccountEligible ),
			{
				action: 'dismiss_x',
				...getTimeToClickProps( viewTimestamp ),
			}
		);
		dismissPrompt();
	}, [ isAccountEligible, viewTimestamp, dismissPrompt ] );

	return (
		<Spotlight
			heading={ __(
				'Enjoying WooPayments so far?',
				'woocommerce-payments'
			) }
			description={ __(
				'Your feedback shapes our roadmap and supports the WooCommerce community. We are all ears!',
				'woocommerce-payments'
			) }
			primaryButtonLabel={ __(
				'Write a review',
				'woocommerce-payments'
			) }
			onPrimaryClick={ handlePrimaryClick }
			secondaryButtonLabel={ __( 'Maybe later', 'woocommerce-payments' ) }
			onSecondaryClick={ handleSecondaryClick }
			onDismiss={ handleDismiss }
			onView={ handleView }
			showImmediately={ false }
		/>
	);
};

export default ReviewPrompt;
