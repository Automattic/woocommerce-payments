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
import { getVariantContent } from './variants';

const marketplaceReviewBaseUrl =
	'https://woocommerce.com/products/woopayments/';

/**
 * Build the Marketplace review URL for the given variant.
 *
 * All merchants are routed to the woocommerce.com Marketplace. WooPayments is a
 * wordpress.org-sourced product there, so a review can be left without a prior
 * purchase, and — unlike wordpress.org — the destination is instrumented, so
 * submissions can be attributed back to this prompt and the assigned design
 * variant. The `review` param opens the review modal on arrival; the `utm_*`
 * params carry attribution for the destination-side funnel, following the
 * standard UTM vocabulary used across WooCommerce and woocommerce.com. The
 * assigned design variant rides in `utm_content` — the canonical UTM slot for
 * distinguishing variations within a single campaign.
 */
const getMarketplaceReviewUrl = ( variant: string ): string => {
	const params = new URLSearchParams( {
		review: '',
		utm_source: 'woopayments',
		utm_medium: 'in_app_review_prompt',
		utm_campaign: 'review_prompt_settings_001',
		utm_content: variant,
	} );

	return `${ marketplaceReviewBaseUrl }?${ params.toString() }`;
};

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
 * Eligibility is always true when this script loads (checked server-side).
 */
const getBaseEventProperties = () => {
	return {
		prompt_id: 'review_prompt_settings_001',
		extension: 'woopayments',
		location: 'payments_settings_top_level',
		trigger: 'none',
		flag_enabled: true,
		version: window.wcpayReviewPromptSettings?.version || 'unknown',
		experiment: window.wcpayReviewPromptSettings?.experiment || 'unknown',
		variant: window.wcpayReviewPromptSettings?.variant || 'control',
	};
};

const ReviewPrompt: React.FC = () => {
	const { dismissPrompt, setMaybeLater } = useReviewPromptState();
	const content = getVariantContent(
		window.wcpayReviewPromptSettings?.variant
	);

	const [ viewTimestamp, setViewTimestamp ] = useState< number | null >(
		null
	);
	const [ isVisible, setIsVisible ] = useState( true );

	const handleView = useCallback( () => {
		const timestamp = Date.now();
		setViewTimestamp( timestamp );
		recordPromptEvent(
			'payments_review_prompt_shown',
			getBaseEventProperties()
		);
	}, [] );

	const handlePrimaryClick = useCallback( async () => {
		// All merchants are routed to the woocommerce.com Marketplace,
		// regardless of connection (live/test) state.
		const variant = window.wcpayReviewPromptSettings?.variant || 'control';
		const reviewUrl = getMarketplaceReviewUrl( variant );

		const baseProps = getBaseEventProperties();
		const eventProps = {
			action: 'write_review',
			destination: 'marketplace',
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

		const windowOpened = window.open( reviewUrl, '_blank' );
		if ( ! windowOpened ) {
			// Make sure the request completes before redirecting away.
			await dismissPrompt();

			// Fallback: navigate away from the current tab.
			window.location.href = reviewUrl;
		} else {
			dismissPrompt();
		}
		setIsVisible( false );
	}, [ viewTimestamp, dismissPrompt ] );

	const handleSecondaryClick = useCallback( () => {
		recordPromptEvent(
			'payments_review_prompt_action',
			getBaseEventProperties(),
			{
				action: 'maybe_later',
				...getTimeToClickProps( viewTimestamp ),
			}
		);
		setMaybeLater();
		setIsVisible( false );
	}, [ viewTimestamp, setMaybeLater ] );

	const handleDismiss = useCallback( () => {
		recordPromptEvent(
			'payments_review_prompt_action',
			getBaseEventProperties(),
			{
				action: 'dismiss_x',
				...getTimeToClickProps( viewTimestamp ),
			}
		);
		dismissPrompt();
		setIsVisible( false );
	}, [ viewTimestamp, dismissPrompt ] );

	if ( ! isVisible ) {
		return null;
	}

	return (
		<Spotlight
			icon={ content.icon }
			image={ content.image }
			heading={ content.heading }
			description={ content.description }
			primaryButtonLabel={
				<>
					{ __( 'Leave review', 'woocommerce-payments' ) }
					<span
						className="components-external-link__icon"
						children={ '\u2197' }
					></span>
				</>
			}
			onPrimaryClick={ handlePrimaryClick }
			secondaryButtonLabel={ __( 'Maybe later', 'woocommerce-payments' ) }
			onSecondaryClick={ handleSecondaryClick }
			onDismiss={ handleDismiss }
			onView={ handleView }
			showImmediately={ false }
			showDelayMs={ 2000 }
			reverseButtons={ true }
		/>
	);
};

export default ReviewPrompt;
