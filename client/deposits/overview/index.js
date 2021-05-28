/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	SummaryListPlaceholder,
	SummaryList,
	SummaryNumber,
} from '@woocommerce/components';
import Gridicon from 'gridicons';

/**
 * Internal dependencies
 */
import './style.scss';
import { useDepositsOverview } from 'data';
import Loadable from 'components/loadable';
import { getDetailsURL } from 'components/details-link';
import { formatCurrency } from 'utils/currency';
import InstantDepositButton from '../instant-deposits';
import {
	getDepositDate,
	getBalanceDepositCount,
	getNextDepositLabelFormatted,
	getDepositScheduleDescriptor,
} from '../utils';

const getAmount = ( obj, defaultCurrency ) => {
	return formatCurrency(
		obj ? obj.amount : 0,
		obj && obj.currency ? obj.currency : defaultCurrency
	);
};

const DepositsOverview = () => {
	const { overview, isLoading } = useDepositsOverview();

	return (
		<div className="wcpay-deposits-overview">
			<div className="wcpay-deposits-overview__header">
				<p className="wcpay-deposits-overview__schedule">
					<Gridicon
						icon="calendar"
						className="wcpay-deposits-overview__schedule-icon"
					/>
					<span className="wcpay-deposits-overview__schedule-label">
						{ __( 'Deposit schedule:', 'woocommerce-payments' ) }
					</span>{ ' ' }
					<span className="wcpay-deposits-overview__schedule-value">
						<Loadable
							isLoading={ isLoading }
							display="inline"
							placeholder="Deposit schedule placeholder"
						>
							{ overview
								? getDepositScheduleDescriptor( overview )
								: '' }
						</Loadable>
					</span>
				</p>
				{ overview && overview.instant_balance && (
					<InstantDepositButton
						instantBalance={ overview.instant_balance }
					/>
				) }
			</div>
			{ isLoading ? (
				<SummaryListPlaceholder numberOfItems={ 4 } />
			) : (
				<SummaryList
					label={ __( 'Deposits overview', 'woocommerce-payments' ) }
				>
					{ () => {
						return [
							<SummaryNumber
								key="lastDeposit"
								label={ __(
									'Last deposit',
									'woocommerce-payments'
								) }
								{ ...( overview && {
									value: getAmount(
										overview.last_deposit,
										overview.account.default_currency
									),
									href: overview.last_deposit
										? getDetailsURL(
												overview.last_deposit.id,
												'deposits'
										  )
										: '',
									children: (
										<span className="wcpay-summary__item-detail">
											{ getDepositDate(
												overview.last_deposit
											) }
										</span>
									),
								} ) }
							/>,
							<SummaryNumber
								key="nextDeposit"
								label={ __(
									'Next deposit',
									'woocommerce-payments'
								) }
								{ ...( overview && {
									value: getAmount(
										overview.next_deposit,
										overview.account.default_currency
									),
									href: overview.next_deposit
										? getDetailsURL(
												overview.next_deposit.id,
												'deposits'
										  )
										: '',
									children: (
										<span className="wcpay-summary__item-detail">
											{ getNextDepositLabelFormatted(
												overview.next_deposit
											) }
										</span>
									),
								} ) }
							/>,
							<SummaryNumber
								key="pendingBalance"
								label={ __(
									'Pending balance',
									'woocommerce-payments'
								) }
								{ ...( overview && {
									value: getAmount(
										overview.balance.pending,
										overview.account.default_currency
									),
									children: (
										<span className="wcpay-summary__item-detail">
											{ getBalanceDepositCount(
												overview.balance.pending
											) }
										</span>
									),
								} ) }
							/>,
							<SummaryNumber
								key="availableBalance"
								label={ __(
									'Available balance',
									'woocommerce-payments'
								) }
								value={
									overview &&
									getAmount(
										overview.balance.available,
										overview.account.default_currency
									)
								}
							/>,
						];
					} }
				</SummaryList>
			) }
		</div>
	);
};

export default DepositsOverview;
