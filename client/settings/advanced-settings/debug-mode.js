/**
 * External dependencies
 */
import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useDebugLog, useDevMode } from 'wcpay/data';

const DebugMode = () => {
	const isDevModeEnabled = useDevMode();
	const [ isLoggingChecked, setIsLoggingChecked ] = useDebugLog();
	const headingRef = useRef( null );

	useEffect( () => {
		if ( ! headingRef.current ) return;

		headingRef.current.focus();
	}, [] );

	return (
		<>
			<h4 ref={ headingRef } tabIndex="-1">
				{ __( 'Debug mode', 'woocommerce-payments' ) }
			</h4>
			<CheckboxControl
				label={
					isDevModeEnabled
						? __(
								'Dev mode is active so logging is on by default.',
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
				onChange={ setIsLoggingChecked }
			/>
		</>
	);
};

export default DebugMode;
