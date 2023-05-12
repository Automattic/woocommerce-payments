/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

const formatDate = ( format: string, date: number | string ) =>
	dateI18n(
		format,
		moment.utc( date ).toISOString(),
		true // TODO Change call to gmdateI18n and remove this deprecated param once WP 5.4 support ends.
	);

interface DepositObject {
	date: number | string;
}
export const getDepositDate = ( deposit: DepositObject | null ): string =>
	deposit ? formatDate( 'F j, Y', deposit?.date ) : '—';

interface GetDepositMonthlyAnchorLabelProps {
	monthlyAnchor: number;
	capitalize?: boolean;
}
export const getDepositMonthlyAnchorLabel = ( {
	monthlyAnchor,
	capitalize = true,
}: GetDepositMonthlyAnchorLabelProps ): string => {
	// If locale is set up as en_US or en_GB the ordinal will not show up
	// More details can be found in https://github.com/WordPress/gutenberg/issues/15221/
	// Using 'en' as the locale should be enough to workaround it
	// TODO: Remove workaround when issue is resolved
	const fixedLocale = moment.locale().startsWith( 'en' )
		? 'en'
		: moment.locale();

	let label = moment()
		.locale( fixedLocale )
		.date( monthlyAnchor )
		.format( 'Do' );

	if ( 31 === monthlyAnchor ) {
		label = __( 'Last day of the month', 'woocommerce-payments' );
	}
	if ( ! capitalize ) {
		label = label.toLowerCase();
	}
	return label;
};
