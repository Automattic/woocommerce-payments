/**
 * External dependencies
 */
import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useContext } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useDebugLog, useDevMode } from 'wcpay/data';
import WCPaySettingsContext from '../wcpay-settings-context';

const DebugMode = () => {
	const isDevModeEnabled = useDevMode();
	const [ isLoggingChecked, setIsLoggingChecked ] = useDebugLog();
	const { setHasChanges } = useContext( WCPaySettingsContext );

	const handleChange = ( value ) => {
		setIsLoggingChecked( value );
		setHasChanges( true );
	};

	return (
		<>
			<h4 tabIndex="-1">
				{ __( 'Debug mode', 'woocommerce-payments' ) }
			</h4>
			<CheckboxControl
				label={
					isDevModeEnabled
						? __(
								'Sandbox mode is active so logging is on by default.',
								'woocommerce-payments'
						  )
						: __( 'Log error messages', 'woocommerce-payments' )
				}
				help={ __(
					'When enabled, payment error logs will be saved to WooCommerce > Status > Logs.',
					'woocommerce-payments'
				) }
				disabled={ isDevModeEnabled }
				checked={ isDevModeEnabled || isLoggingChecked }
				onChange={ handleChange }
			/>
		</>
	);
};

export default DebugMode;
