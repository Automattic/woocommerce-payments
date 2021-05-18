/**
 * External dependencies
 */
import { useState, useCallback, useEffect, useRef } from '@wordpress/element';
import { Icon, chevronDown, chevronUp } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CheckboxControl,
	TextControl,
	TextareaControl,
	Notice,
	Button,
	ExternalLink,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import SettingsSection from '../settings-section';
import './style.scss';

const useToggle = ( initialValue = false ) => {
	const [ value, setValue ] = useState( initialValue );
	const toggleValue = useCallback(
		() => setValue( ( oldValue ) => ! oldValue ),
		[ setValue ]
	);

	return [ value, toggleValue ];
};

const AdvancedSettings = () => {
	const [ isSectionExpanded, toggleIsSectionExpanded ] = useToggle( false );
	const firstHeadingElementRef = useRef( null );
	const [ isLoggingChecked, setIsLoggingChecked ] = useState( false );
	const [ customFontValue, setCustomFontValue ] = useState( '' );
	const [ cssStylingValue, setCssStylingValue ] = useState( '' );

	useEffect( () => {
		if ( ! isSectionExpanded ) return;
		if ( ! firstHeadingElementRef.current ) return;

		firstHeadingElementRef.current.focus();
	}, [ isSectionExpanded ] );

	return (
		<>
			<SettingsSection>
				<Button onClick={ toggleIsSectionExpanded } isTertiary>
					{ __( 'Advanced settings', 'wordpress-components' ) }
					<Icon
						icon={ isSectionExpanded ? chevronUp : chevronDown }
					/>
				</Button>
			</SettingsSection>
			{ isSectionExpanded && (
				<SettingsSection>
					<Card>
						<CardBody size="large">
							<h4 ref={ firstHeadingElementRef } tabIndex="-1">
								Debug mode
							</h4>
							<CheckboxControl
								label={ __(
									'Log error messages',
									'woocommerce-payments'
								) }
								help={ __(
									'When enabled, payment error logs will be saved to WooCommerce > Status > Logs.',
									'woocommerce-payments'
								) }
								checked={ isLoggingChecked }
								onChange={ setIsLoggingChecked }
							/>
							<h4>
								{ __(
									'Block appearance',
									'woocommerce-payments'
								) }
							</h4>
							<p>
								{ __(
									'Customize or override the appearance of the payment methods block on checkout.',
									'woocommerce-payments'
								) }{ ' ' }
								<ExternalLink href="https://stripe.com/docs/stripe-js/payment-element">
									{ __(
										'Learn how',
										'woocommerce-payments'
									) }
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
							<TextControl
								label={ __(
									'Custom font',
									'woocommerce-payments'
								) }
								value={ customFontValue }
								onChange={ setCustomFontValue }
								autoComplete="off"
							/>
							<TextareaControl
								label={ __(
									'Additional CSS styling',
									'woocommerce-payments'
								) }
								value={ cssStylingValue }
								onChange={ setCssStylingValue }
							/>
						</CardBody>
					</Card>
				</SettingsSection>
			) }
		</>
	);
};

export default AdvancedSettings;
