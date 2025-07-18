/**
 * External dependencies
 */
import React, { useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { Modal } from 'wcpay/components/wp-components-wrapped/components/modal';
import { dispatch } from '@wordpress/data';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import { recordEvent } from 'wcpay/tracks';
import './style.scss';
import { ExternalLink } from 'wcpay/components/wp-components-wrapped/components/external-link';

interface NegativeFeedbackModalProps {
	onRequestClose: () => void;
}

export const NegativeFeedbackModal: React.FC< NegativeFeedbackModalProps > = ( {
	onRequestClose,
} ) => {
	const textareaRef = useRef< HTMLTextAreaElement >( null );
	// Record tracks event when the modal is opened.
	useEffect( () => {
		recordEvent( 'wcpay_merchant_feedback_prompt_negative_modal_view' );
	}, [] );

	return (
		<Modal
			title={ __( 'Share your feedback', 'woocommerce-payments' ) }
			className="wcpay-merchant-feedback-negative-modal"
			isDismissible={ true }
			shouldCloseOnClickOutside={ false } // Should be false because of the iframe.
			shouldCloseOnEsc={ true }
			onRequestClose={ () => {
				recordEvent(
					'wcpay_merchant_feedback_prompt_negative_modal_close_click'
				);
				onRequestClose();
			} }
		>
			<div className="wcpay-merchant-feedback-negative-modal__content">
				<p>
					{ __(
						'Thanks for sharing your feedback on WooPayments! Your feedback helps us to continue to improve and deliver the best tools for your business.',
						'woocommerce-payments'
					) }
				</p>
				<p className="wcpay-merchant-feedback-negative-modal__question">
					{ __(
						'Would you mind sharing more about why you chose that option?',
						'woocommerce-payments'
					) }
				</p>
				<textarea
					ref={ textareaRef }
					className="wcpay-merchant-feedback-negative-modal__textarea"
					placeholder={ __(
						'Share your feedback here…',
						'woocommerce-payments'
					) }
				/>
				<p className="wcpay-merchant-feedback-negative-modal__privacy">
					{ interpolateComponents( {
						// translators: {{a}}: placeholders are opening and closing anchor tags.
						mixedString: __(
							'Your feedback will be sent to the WooCommerce team. Your personal information is secure and will not be shared with third parties. For more details, please see our {{a}}privacy policy{{/a}}.',
							'woocommerce-payments'
						),
						components: {
							a: (
								<ExternalLink href="https://automattic.com/privacy/" />
							),
						},
					} ) }
				</p>
				<p>
					{ interpolateComponents( {
						// translators: {{a}}: placeholders are opening and closing anchor tags.
						mixedString: __(
							`Need help with a specific issue? {{a}}Contact our support team{{/a}} for personalized assistance.`,
							'woocommerce-payments'
						),
						components: {
							a: (
								<ExternalLink
									// Link to the WooCommerce support form with WooPayments selected.
									href="https://woocommerce.com/my-account/contact-support/?select=5278104"
									onClick={ () => {
										recordEvent(
											'wcpay_merchant_feedback_prompt_negative_modal_contact_support_click'
										);
									} }
								/>
							),
						},
					} ) }
				</p>
				<div className="wcpay-merchant-feedback-negative-modal__actions">
					<button
						className="components-button"
						onClick={ () => {
							recordEvent(
								'wcpay_merchant_feedback_prompt_negative_modal_close_click'
							);
							onRequestClose();
						} }
					>
						{ __( 'Close', 'woocommerce-payments' ) }
					</button>
					<button
						className="components-button is-primary"
						onClick={ () => {
							recordEvent(
								'wcpay_merchant_feedback_prompt_negative_feedback',
								{ feedback: textareaRef.current?.value || '' }
							);
							dispatch( 'core/notices' ).createSuccessNotice(
								__(
									'Thank you for your feedback!',
									'woocommerce-payments'
								)
							);
							onRequestClose();
						} }
					>
						{ __( 'Send', 'woocommerce-payments' ) }
					</button>
				</div>
			</div>
		</Modal>
	);
};
