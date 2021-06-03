/**
 * External dependencies
 */
import { useEffect, useRef } from '@wordpress/element';
import { Icon, chevronDown, chevronUp } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CheckboxControl, Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SettingsSection from '../settings-section';
import BlockAppearance from './block-appearance';
import useToggle from './use-toggle';
import './style.scss';
import { useDebugLog, useDevMode } from '../../data';

const AdvancedSettings = () => {
	const isDevModeEnabled = useDevMode();
	const [ isSectionExpanded, toggleIsSectionExpanded ] = useToggle( false );
	const firstHeadingElementRef = useRef( null );
	const [ isLoggingChecked, setIsLoggingChecked ] = useDebugLog();

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
								label={
									isDevModeEnabled
										? __(
												'Dev mode is active so logging is on by default.',
												'woocommerce-payments'
										  )
										: __(
												'Log error messages',
												'woocommerce-payments'
										  )
								}
								help={ __(
									'When enabled, payment error logs will be saved to WooCommerce > Status > Logs.',
									'woocommerce-payments'
								) }
								disabled={ isDevModeEnabled }
								checked={ isDevModeEnabled || isLoggingChecked }
								onChange={ setIsLoggingChecked }
							/>
							<BlockAppearance />
						</CardBody>
					</Card>
				</SettingsSection>
			) }
		</>
	);
};

export default AdvancedSettings;
