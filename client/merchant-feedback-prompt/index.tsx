/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
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
import { PositiveFeedbackModal } from './positive-modal';
import { NegativeFeedbackModal } from './negative-modal';
import { useMerchantFeedbackPromptState } from './hooks';
import './style.scss';

/**
 * HACK: This file contains temporary workarounds to allow us to render a snackbar with two actions and a dismiss button.
 *
 * Workarounds include:
 * - Using a React portal to render a custom Snackbar within a SnackbarList consistently with other core WP-admin notices – needed because `core/notices` `createNotice()` doesn't accept two actions.
 *   - See https://github.com/WordPress/gutenberg/blob/c300edfebb48f79f6f0f6643ce04dd73303c5fcb/packages/components/src/snackbar/index.tsx#L119-L126
 * - Adding a dismiss button to the custom Snackbar component – needed because the Snackbar component bundled by WooPayments doesn't have an `explicitDismiss` prop.
 *   - See https://github.com/WordPress/gutenberg/blob/c300edfebb48f79f6f0f6643ce04dd73303c5fcb/packages/components/src/snackbar/index.tsx#L166-L177
 * - Checking for the presence of WP-admin core notices to ensure that this prompt is not rendered if there are other notices being displayed.
 *
 * These temporary workarounds will remain in place until either:
 * - This code is removed at the end of the campaign (paJDYF-gvt-p2), or
 * - Gutenberg Snackbar component is updated to accept two actions and we can use `core/notices` `createNotice()` to render the snackbar.
 */

/**
 * A react portal for the merchant feedback prompt.
 * This is used to render the custom snackbar prompt in the WC footer component, consistent with where WC notices (snackbars) are rendered.
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
	/** A function to be called when the user clicks the "Yes" button and the positive feedback modal is to be shown. */
	showPositiveFeedbackModal: () => void;
	/** A function to be called when the user clicks the "No" button and the negative feedback modal is to be shown. */
	showNegativeFeedbackModal: () => void;
}

/**
 * Renders the merchant feedback prompt (snackbar) in the WC footer.
 *
 * This is used to gather feedback from merchants about their experience with WooPayments.
 * Only renders if there are no core notices and the prompt has not been dismissed.
 */
const MerchantFeedbackPrompt: React.FC< MerchantFeedbackPromptProps > = ( {
	dismissPrompt,
	showPositiveFeedbackModal,
	showNegativeFeedbackModal,
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
											showPositiveFeedbackModal();
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
											showNegativeFeedbackModal();
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
 * A wrapper component that conditionally renders the merchant feedback prompt, including the positive and negative feedback modals.
 *
 * This is used to ensure the prompt is only rendered if the account is eligible for the campaign and the user has not dismissed the prompt.
 */
export function MaybeShowMerchantFeedbackPrompt() {
	const {
		isAccountEligible,
		hasUserDismissedPrompt,
		dismissPrompt,
	} = useMerchantFeedbackPromptState();

	const [
		isPositiveFeedbackModalOpen,
		setIsPositiveFeedbackModalOpen,
	] = useState( false );

	const [
		isNegativeFeedbackModalOpen,
		setIsNegativeFeedbackModalOpen,
	] = useState( false );

	if ( isPositiveFeedbackModalOpen ) {
		return (
			<PositiveFeedbackModal
				onRequestClose={ () => setIsPositiveFeedbackModalOpen( false ) }
			/>
		);
	}

	if ( isNegativeFeedbackModalOpen ) {
		return (
			<NegativeFeedbackModal
				onRequestClose={ () => setIsNegativeFeedbackModalOpen( false ) }
			/>
		);
	}

	if ( hasUserDismissedPrompt || ! isAccountEligible ) {
		return null;
	}

	return (
		<MerchantFeedbackPrompt
			dismissPrompt={ dismissPrompt }
			showPositiveFeedbackModal={ () =>
				setIsPositiveFeedbackModalOpen( true )
			}
			showNegativeFeedbackModal={ () => {
				if ( window.wcTracks.isEnabled ) {
					setIsNegativeFeedbackModalOpen( true );
				} else {
					dismissPrompt();
				}
			} }
		/>
	);
}
