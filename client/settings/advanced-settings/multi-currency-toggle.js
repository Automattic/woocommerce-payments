/**
 * External dependencies
 */
import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useMultiCurrency } from 'wcpay/data';

const MultiCurrencyToggle = () => {
	const [
		isMultiCurrencyEnabled,
		updateIsMultiCurrencyEnabled,
	] = useMultiCurrency();

	const headingRef = useRef( null );

	useEffect( () => {
		if ( ! headingRef.current ) return;

		headingRef.current.focus();
	}, [] );

	const handleMultiCurrencyStatusChange = ( value ) => {
		updateIsMultiCurrencyEnabled( value );
	};

	return (
		<>
			<h4 ref={ headingRef } tabIndex="-1">
				{ __(
					'Enable Customer Multi Currency',
					'woocommerce-payments'
				) }
			</h4>
			<CheckboxControl
				label={ __(
					'Allow your customers to shop and pay in their local currency.',
					'woocommerce-payments'
				) }
				help={
					! isMultiCurrencyEnabled
						? __(
								'By enabling this, a new tab, "Multi-currency" will appear on the ' +
									'WooCommerce Settings page, and you will be able to configure this ' +
									'feature to suit your needs.',
								'woocommerce-payments'
						  )
						: __(
								'By disabling this, the "Multi-currency" tab, added by our feature, ' +
									"will be hidden from your settings page, and you won't be able to use " +
									'this feature in your store.',
								'woocommerce-payments'
						  )
				}
				checked={ isMultiCurrencyEnabled }
				onChange={ handleMultiCurrencyStatusChange }
			/>
		</>
	);
};

export default MultiCurrencyToggle;
