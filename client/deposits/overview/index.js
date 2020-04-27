/**
 * External dependencies
 */
import { SummaryListPlaceholder, SummaryList, SummaryNumber } from '@woocommerce/components';
import Currency from '@woocommerce/currency';
import { __, sprintf, _n } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import Gridicon from 'gridicons';
import moment from 'moment';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';

/**
 * Internal dependencies
 */
import './style.scss';
import { useDepositsOverview } from 'data';
import Loadable from 'components/loadable';
import { getDetailsURL } from 'components/details-link';

const currency = new Currency();
const formatDate = ( format, date ) => dateI18n( format, moment.utc( date ).local() );
const getAmount = ( obj ) => currency.formatCurrency( ( obj ? obj.amount : 0 ) / 100 );
const getDepositDate = ( deposit ) => deposit ? formatDate( 'F j, Y', deposit.date ) : '—';
const getBalanceDepositCount = ( balance ) =>
	sprintf( _n( '%d deposit', '%d deposits', balance.deposits_count, 'woocommerce-payments' ), balance.deposits_count );
const getNextDepositLabelFormatted = ( deposit ) => {
	const baseLabel = ( deposit ) ? `${ __( 'Est.', 'woocommerce-payments' ) } ${ formatDate( 'M j, Y', deposit.date ) }` : '—';
	if ( deposit && 'in_transit' === deposit.status ) {
		return `${ baseLabel } - ${ __( 'In transit', 'woocommerce-payments' ) }`;
	}
	return baseLabel;
};

const formatDepositSchedule = schedule => {
	switch ( schedule.interval ) {
		case 'daily':
			return __( 'Automatic, every business day', 'woocommerce-payments' );
		case 'weekly':
			return sprintf(
				/** translators: %s day of the week e.g. Monday */
				__( 'Automatic, every week on %s', 'woocommerce-payments' ),
				// moment().day() is locale based when using strings. Since Stripe's response is in English,
				// we need to temporarily set the locale to add the day before formatting
				moment().locale( 'en' ).day( schedule.weekly_anchor ).locale( moment.locale() ).format( 'dddd' )
			);
		case 'monthly':
			// If locale is set up as en_US or en_GB the ordinal will not show up
			// More details can be found in https://github.com/WordPress/gutenberg/issues/15221/
			// Using 'en' as the locale should be enough to workaround it
			// TODO: Remove workaround when issue is resolved
			const fixedLocale = moment.locale().startsWith( 'en' ) ? 'en' : moment.locale();
			return sprintf(
				/** translators: %s day of the month */
				__( 'Automatic, every month on the %s', 'woocommerce-payments' ),
				moment().locale( fixedLocale ).date( schedule.monthly_anchor ).format( 'Do' )
			);
	}
};

const getDepositScheduleDescriptor = ( { account: { deposits_schedule: schedule, deposits_disabled: disabled }, last_deposit: last } ) => {
	if ( disabled || 'manual' === schedule.interval ) {
		return createInterpolateElement(
			/* translators: <a> - suspended accounts FAQ URL */
			__( 'Temporarily suspended (<a>Learn more</a>)', 'woocommerce-payments' ),
			// eslint-disable-next-line jsx-a11y/anchor-has-content
			{ a: <a href="https://docs.woocommerce.com/document/payments/faq/deposits-suspended/" /> }
		);
	}

	if ( ! last ) {
		return sprintf(
			createInterpolateElement(
				/** translators: %s - deposit schedule, <a> - waiting period doc URL */
				__( '%s (Your first deposit is held for seven days. <a>Learn more</a>)', 'woocommerce-payments' ),
				// eslint-disable-next-line jsx-a11y/anchor-has-content
				{ a: <a href="https://docs.woocommerce.com/document/payments/faq/deposit-schedule/" /> }
			),
			formatDepositSchedule( schedule )
		);
	}

	return formatDepositSchedule( schedule );
};

const DepositsOverview = () => {
	const { overview, isLoading } = useDepositsOverview();
	return (
		<div className="wcpay-deposits-overview">
			<p className="wcpay-deposits-overview__schedule">
				<Gridicon icon="calendar" className="wcpay-deposits-overview__schedule-icon" />
				<span className="wcpay-deposits-overview__schedule-label">{ __( 'Deposit schedule:', 'woocommerce-payments' ) }</span>
				{ ' ' }
				<span className="wcpay-deposits-overview__schedule-value">
					<Loadable isLoading={ isLoading || ! overview } display="inline" placeholder="Deposit schedule placeholder">
						{ overview ? getDepositScheduleDescriptor( overview ) : '' }
					</Loadable>
				</span>
			</p>
			{ isLoading || ! overview
				? <SummaryListPlaceholder numberOfItems={ 4 } />
				: <SummaryList label={ __( 'Deposits overview', 'woocommerce-payments' ) }>
					{ () => {
						return [
							<SummaryNumber
								key="lastDeposit"
								label={ __( 'Last deposit', 'woocommerce-payments' ) }
								value={ getAmount( overview.last_deposit ) }
								prevLabel={ getDepositDate( overview.last_deposit ) }
								href={ overview.last_deposit ? getDetailsURL( overview.last_deposit.id, 'deposits' ) : '' }
							/>,
							<SummaryNumber
								key="nextDeposit"
								label={ __( 'Next deposit', 'woocommerce-payments' ) }
								value={ getAmount( overview.next_deposit ) }
								prevLabel={ getNextDepositLabelFormatted( overview.next_deposit ) }
								href={ overview.next_deposit ? getDetailsURL( overview.next_deposit.id, 'deposits' ) : '' }
							/>,
							<SummaryNumber
								key="pendingBalance"
								label={ __( 'Pending balance', 'woocommerce-payments' ) }
								value={ getAmount( overview.balance.pending ) }
								prevLabel={ getBalanceDepositCount( overview.balance.pending ) }
							/>,
							<SummaryNumber
								key="availableBalance"
								label={ __( 'Available balance', 'woocommerce-payments' ) }
								value={ getAmount( overview.balance.available ) }
								prevLabel=""
							/>,
						];
					} }
				</SummaryList> }
		</div>
	);
};

export default DepositsOverview;
