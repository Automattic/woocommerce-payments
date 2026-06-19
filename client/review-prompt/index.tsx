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
import { MerchantEvent } from 'wcpay/tracks/event';
import { getVariantContent } from './variants';

const marketplaceReviewBaseUrl =
	'https://woocommerce.com/products/woopayments/';

/**
 * Build the Marketplace review URL with UTM attribution for the assigned variant.
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

const recordPromptEvent = (
	eventName: MerchantEvent,
	baseProperties: Record< string, unknown >,
	additionalProperties?: Record< string, unknown >
) => {
	recordEvent( eventName, { ...baseProperties, ...additionalProperties } );
};

const getTimeToClickProps = (
	viewTimestamp: number | null
): Record< string, number > => {
	const timeToClickMs = viewTimestamp ? Date.now() - viewTimestamp : null;
	return timeToClickMs !== null ? { time_to_click_ms: timeToClickMs } : {};
};

/**
 * Base prompt telemetry props. The script only loads after server-side eligibility passes.
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
			'wcpay_review_prompt_shown',
			getBaseEventProperties()
		);
	}, [] );

	const handlePrimaryClick = useCallback( async () => {
		const variant = window.wcpayReviewPromptSettings?.variant || 'control';
		const reviewUrl = getMarketplaceReviewUrl( variant );

		const baseProps = getBaseEventProperties();
		const eventProps = {
			action: 'write_review',
			destination: 'marketplace',
			...getTimeToClickProps( viewTimestamp ),
		};

		recordPromptEvent(
			'wcpay_review_prompt_action',
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
			'wcpay_review_prompt_action',
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
			'wcpay_review_prompt_action',
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
