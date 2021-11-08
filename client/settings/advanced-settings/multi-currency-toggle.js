/**
 * External dependencies
 */
import { CheckboxControl, ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useMultiCurrency } from 'wcpay/data';
import interpolateComponents from 'interpolate-components';

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
		<CheckboxControl
			label={ __( 'Enable Multi-Currency', 'woocommerce-payments' ) }
			help={ interpolateComponents( {
				mixedString: __(
					'Allow customers to shop and pay in multiple currencies. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
					'woocommerce-payments'
				),
				components: {
					learnMoreLink: (
						// eslint-disable-next-line max-len
						<ExternalLink href="https://woocommerce.com/document/payments/currencies/multi-currency-setup" />
					),
				},
			} ) }
			checked={ isMultiCurrencyEnabled }
			onChange={ handleMultiCurrencyStatusChange }
		/>
	);
};

export default MultiCurrencyToggle;
