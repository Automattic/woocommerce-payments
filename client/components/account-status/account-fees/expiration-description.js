/** @format */

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

/**
 * Internal dependencies
 */
import { formatCurrency } from 'utils/currency';

const ExpirationDescription = ( {
	feeData: { volume_allowance: volumeAllowance, end_time: endTime, ...rest },
} ) => {
	const currencyCode = rest.volume_currency ?? rest.currency;

	let description;
	if ( volumeAllowance && endTime ) {
		description = sprintf(
			/* translators: %1: total payment volume until this promotion expires %2: End date of the promotion */
			__(
				'Discounted base fee expires after the first %1$s of total payment volume or on %2$s.',
				'woocommerce-payments'
			),
			formatCurrency( volumeAllowance, currencyCode ),
			dateI18n( 'F j, Y', moment( endTime ).toISOString() )
		);
	} else if ( volumeAllowance ) {
		description = sprintf(
			/* translators: %1: total payment volume until this promotion expires */
			__(
				'Discounted base fee expires after the first %1$s of total payment volume.',
				'woocommerce-payments'
			),
			formatCurrency( volumeAllowance, currencyCode )
		);
	} else if ( endTime ) {
		description = sprintf(
			/* translators: %1: End date of the promotion */
			__(
				'Discounted base fee expires on %1$s.',
				'woocommerce-payments'
			),
			dateI18n( 'F j, Y', moment( endTime ).toISOString() )
		);
	} else {
		return null;
	}
	return <p className="description">{ description }</p>;
};

export default ExpirationDescription;
