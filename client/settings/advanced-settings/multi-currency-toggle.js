/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { CheckboxControl } from 'wcpay/components/wp-components-wrapped/components/checkbox-control';
import { ExternalLink } from 'wcpay/components/wp-components-wrapped/components/external-link';
import { useMultiCurrency } from 'wcpay/data';
import interpolateComponents from '@automattic/interpolate-components';

const MultiCurrencyToggle = () => {
	const [
		isMultiCurrencyEnabled,
		updateIsMultiCurrencyEnabled,
	] = useMultiCurrency();

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
						<ExternalLink href="https://woocommerce.com/document/woopayments/currencies/multi-currency-setup/" />
					),
				},
			} ) }
			checked={ isMultiCurrencyEnabled }
			onChange={ handleMultiCurrencyStatusChange }
			data-testid="multi-currency-toggle"
			__nextHasNoMarginBottom
		/>
	);
};

export default MultiCurrencyToggle;
