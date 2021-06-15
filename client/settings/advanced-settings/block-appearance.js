/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	ExternalLink,
	Notice,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import CheckboxToggle from './checkbox-toggle';

const BlockAppearance = () => {
	const [ customFontValue, setCustomFontValue ] = useState( '' );
	const [ cssStylingValue, setCssStylingValue ] = useState( '' );

	return (
		<>
			<h4>{ __( 'Block appearance', 'woocommerce-payments' ) }</h4>
			<p>
				{ __(
					'Customize or override the appearance of the payment methods block on checkout.',
					'woocommerce-payments'
				) }{ ' ' }
				<ExternalLink href="https://stripe.com/docs/stripe-js/payment-element">
					{ __( 'Learn how', 'woocommerce-payments' ) }
				</ExternalLink>
			</p>
			<Notice
				className="advanced-settings__theme-notice"
				status="warning"
				isDismissible={ false }
			>
				{ __(
					"We couldn't find your store’s theme font to use in this block " +
						"so we've replaced it with a fitting alternative. " +
						"If you want to use your custom font, enter a link to where it's located.",
					'woocommerce-payments'
				) }
			</Notice>
			<div className="advanced-settings__custom-font-url-wrapper">
				<CheckboxToggle
					label={ __( 'Use a custom font', 'woocommerce-payments' ) }
				>
					<TextControl
						label={ __(
							'Custom font URL',
							'woocommerce-payments'
						) }
						value={ customFontValue }
						onChange={ setCustomFontValue }
						autoComplete="off"
						placeholder={ sprintf(
							/* translators: %s - example of URL for custom font - text is used as placeholder */
							__( 'Example: %1$s', 'woocommerce-payments' ),
							'https://fonts.googleapis.com/css?family=Source+Sans+Pro'
						) }
						type="url"
						hideLabelFromVision
					/>
				</CheckboxToggle>
			</div>
			<CheckboxToggle
				label={ __( 'Add CSS styling', 'woocommerce-payments' ) }
			>
				<TextareaControl
					label={ __(
						'Additional CSS styling',
						'woocommerce-payments'
					) }
					value={ cssStylingValue }
					onChange={ setCssStylingValue }
					hideLabelFromVision
				/>
			</CheckboxToggle>
		</>
	);
};

export default BlockAppearance;
