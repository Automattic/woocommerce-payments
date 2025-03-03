/**
 * External dependencies
 */
import React, { useEffect, useRef } from 'react';
import ReactDOM from 'react-dom';
import {
	Button,
	Flex,
	FlexItem,
	Icon,
	NoticeList,
	SnackbarList,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { recordEvent } from 'wcpay/tracks';
import { useMerchantFeedbackPromptState } from './hooks';
import './style.scss';

/**
 * A react portal for the merchant feedback prompt.
 * This is used to render the custom snackbar prompt in the WC footer component, consistent with where WC notices (snackbars) are rendered.
 *
 * HACK: This is a temporary solution until Gutenberg Snackbar component and `createNotice` accept two actions.
 *
 * This temporary workaround will remain in place until either:
 * - Gutenberg Snackbar component is updated to accept two actions and we can use `core/notices` `createNotice()` to render the snackbar, or
 * - The campaign for this prompt is complete and we can remove this code entirely.
 */
const WCFooterPortal = ( { children }: { children: React.ReactNode } ) => {
	const portalRoot = document.getElementsByClassName(
		'woocommerce-layout__footer'
	)[ 0 ];

	if ( ! portalRoot ) {
		return null;
	}

	return ReactDOM.createPortal( children, portalRoot );
};

interface MerchantFeedbackPromptProps {
	/** A function to be called when the user dismisses the prompt and it is to be removed. */
	dismissPrompt: () => void;
}

/**
 * Renders the merchant feedback prompt (snackbar) in the WC footer.
 *
 * This is used to gather feedback from merchants about their experience with WooPayments.
 * Only renders if there are no core notices and the prompt has not been dismissed.
 */
const MerchantFeedbackPrompt: React.FC< MerchantFeedbackPromptProps > = ( {
	dismissPrompt,
} ) => {
	// Get the core notices, which we'll use to ensure we're not rendering the prompt if there are other notices being displayed.
	const coreNotices = useSelect(
		( select ) =>
			select( 'core/notices' ).getNotices() as NoticeList.Notice[]
	);

	// Only render the prompt if there are no core notices.
	const shouldShowPrompt = coreNotices?.length === 0;

	useEffect( () => {
		// Record the 'view' event when the prompt is rendered.
		if ( shouldShowPrompt ) {
			recordEvent( 'wcpay_merchant_feedback_prompt_view' );
		}
	}, [ shouldShowPrompt ] );

	if ( ! shouldShowPrompt ) {
		return null;
	}

	return (
		<WCFooterPortal>
			<SnackbarList
				className="wcpay-merchant-feedback-prompt-wrap"
				notices={ [
					{
						id: 'wcpay-merchant-feedback-prompt',
						className: 'wcpay-merchant-feedback-prompt',
						/**
						 * HACK: This custom content for the snackbar is required because the Snackbar / Snackbar component doesn't accept two actions.
						 *
						 * This temporary workaround will remain in place until either:
						 * - Gutenberg Snackbar component is updated to accept two actions and we can use `core/notices` `createNotice()` to render the snackbar, or
						 * - The campaign for this prompt is complete and we can remove this code entirely.
						 *
						 * See https://github.com/WordPress/gutenberg/blob/c300edfebb48f79f6f0f6643ce04dd73303c5fcb/packages/components/src/snackbar/index.tsx#L119-L126
						 */
						content: (
							<Flex
								gap={ 3 }
								align="center"
								onClick={ dismissPrompt }
							>
								<FlexItem>
									{ __(
										'Are you satisfied with WooPayments?',
										'woocommerce-payments'
									) }
								</FlexItem>
								<FlexItem>
									<Button
										variant="link"
										className="wcpay-merchant-feedback-prompt__action"
										onClick={ () => {
											recordEvent(
												'wcpay_merchant_feedback_prompt_yes_click'
											);
											dismissPrompt();
										} }
									>
										<Icon
											icon={ 'thumbs-up' }
											aria-label={ __(
												'"Yes" icon',
												'woocommerce-payments'
											) }
										/>
										<span className="wcpay-merchant-feedback-prompt__action-label">
											{ __(
												'Yes',
												'woocommerce-payments'
											) }
										</span>
									</Button>
								</FlexItem>
								<FlexItem>
									<Button
										variant="link"
										className="wcpay-merchant-feedback-prompt__action"
										onClick={ () => {
											recordEvent(
												'wcpay_merchant_feedback_prompt_no_click'
											);
											dismissPrompt();
										} }
									>
										<Icon
											icon={ 'thumbs-down' }
											aria-label={ __(
												'"No" icon',
												'woocommerce-payments'
											) }
										/>
										<span className="wcpay-merchant-feedback-prompt__action-label">
											{ __(
												'No',
												'woocommerce-payments'
											) }
										</span>
									</Button>
								</FlexItem>

								{ /*
									Explicit dismiss button, in place of missing `explicitDismiss` prop.
									We need to implement manually because we use an outdated bundled SnackbarList component which doesn't have the `explicitDismiss` prop.
									See https://github.com/WordPress/gutenberg/blob/c300edfebb48f79f6f0f6643ce04dd73303c5fcb/packages/components/src/snackbar/index.tsx#L166-L177
								*/ }
								<FlexItem>
									<span
										role="button"
										aria-label={ __(
											'Dismiss',
											'woocommerce-payments'
										) }
										tabIndex={ 0 }
										onClick={ () => {
											recordEvent(
												'wcpay_merchant_feedback_prompt_dismiss'
											);
											dismissPrompt();
										} }
										onKeyPress={ () => {
											recordEvent(
												'wcpay_merchant_feedback_prompt_dismiss'
											);
											dismissPrompt();
										} }
									>
										{ /* Unicode character for "close" icon */ }
										&#x2715;
									</span>
								</FlexItem>
							</Flex>
						),
					},
				] }
			/>
		</WCFooterPortal>
	);
};

/**
 * A wrapper component that conditionally renders the merchant feedback prompt.
 *
 * This is used to ensure the prompt is only rendered if the account is eligible for the campaign and the user has not dismissed the prompt.
 */
export function MaybeShowMerchantFeedbackPrompt() {
	const {
		isAccountEligible,
		hasUserDismissedPrompt,
		dismissPrompt,
	} = useMerchantFeedbackPromptState();

	if ( hasUserDismissedPrompt || ! isAccountEligible ) {
		return null;
	}

	return <MerchantFeedbackPrompt dismissPrompt={ dismissPrompt } />;
}
