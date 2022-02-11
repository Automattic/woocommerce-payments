/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardHeader, Flex } from '@wordpress/components';
import { Link } from '@woocommerce/components';
import Gridicon from 'gridicons';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import DepositsInformationLoading from './loading';
import { getDetailsURL } from 'components/details-link';
import {
	getBalanceDepositCount,
	getDepositScheduleDescriptor,
	getDepositDate,
	getNextDepositLabelFormatted,
} from 'deposits/utils';
import InstantDepositButton from 'deposits/instant-deposits';
import DepositsInformationBlock from './block';
import { formatCurrency, formatCurrencyName } from 'utils/currency';
import { useAllDepositsOverviews } from 'wcpay/data';

import './style.scss';

interface OverviewProps {
	overview: AccountOverview.Overview;
	account: AccountOverview.Account;
}

/**
 * Renders a deposits overview
 *
 * @param {AccountOverview.Overview} props Deposits overview
 * @return {JSX.Element} Rendered element with deposits overview
 */
const DepositsInformationOverview: React.FunctionComponent< OverviewProps > = (
	props
) => {
	const { overview, account }: OverviewProps = props;
	const {
		currency,
		pending,
		nextScheduled,
		lastPaid,
		available,
		instant,
	} = overview;

	const pendingAmount = pending ? pending.amount : 0;
	const pendingDepositsLink = pending?.deposits_count ? (
		<Link href={ getDetailsURL( nextScheduled.id, 'deposits' ) }>
			{ getBalanceDepositCount( pending ) }
		</Link>
	) : (
		''
	);

	const nextScheduledAmount = nextScheduled ? nextScheduled.amount : 0;
	const nextScheduledLink = nextScheduled && (
		<Link href={ getDetailsURL( nextScheduled.id, 'deposits' ) }>
			{ getNextDepositLabelFormatted( nextScheduled ) }
		</Link>
	);

	const lastPaidAmount = lastPaid ? lastPaid.amount : 0;
	const lastPaidLink = lastPaid && (
		<Link href={ getDetailsURL( lastPaid.id, 'deposits' ) }>
			{ getDepositDate( lastPaid ) }
		</Link>
	);

	const availableAmount = available ? available.amount : 0;

	const scheduleDescriptor = getDepositScheduleDescriptor( {
		account,
		last_deposit: lastPaid,
	} );

	return (
		<Card>
			<CardHeader
				size="small"
				className="wcpay-deposits-information-header"
			>
				{ /* This div will be used for a proper layout next to the button. */ }
				<div className="wcpay-deposits-information-header__heading">
					<h3 className="wcpay-deposits-information-header__title">
						{ sprintf(
							__( '%s balance', 'woocommerce-payments' ),
							formatCurrencyName( currency )
						) }
					</h3>

					<p className="wcpay-deposits-information-header__schedule">
						<Gridicon
							icon="calendar"
							size={ 24 }
							className="wcpay-deposits-information-header__icon"
						/>
						{ __( 'Deposit schedule:', 'woocommerce-payments' ) }{ ' ' }
						{ scheduleDescriptor }
					</p>
				</div>

				{ instant && (
					<InstantDepositButton instantBalance={ instant } />
				) }
			</CardHeader>

			<Flex className="wcpay-deposits-information-row" align="normal">
				<DepositsInformationBlock
					title={ __( 'Pending balance', 'woocommerce-payments' ) }
					value={ formatCurrency( pendingAmount, currency ) }
					children={ pendingDepositsLink }
				/>
				<DepositsInformationBlock
					title={ __( 'Next deposit', 'woocommerce-payments' ) }
					value={ formatCurrency( nextScheduledAmount, currency ) }
					children={ nextScheduledLink }
				/>
			</Flex>
			<Flex className="wcpay-deposits-information-row" align="normal">
				<DepositsInformationBlock
					title={ __( 'Last deposit', 'woocommerce-payments' ) }
					value={ formatCurrency( lastPaidAmount, currency ) }
					children={ lastPaidLink }
				/>
				<DepositsInformationBlock
					title={ __( 'Available balance', 'woocommerce-payments' ) }
					value={ formatCurrency( availableAmount, currency ) }
				/>
			</Flex>
		</Card>
	);
};

const DepositsInformation = (): JSX.Element => {
	const {
		overviews,
		isLoading,
	} = useAllDepositsOverviews() as AccountOverview.OverviewsResponse;

	if ( isLoading ) {
		return <DepositsInformationLoading />;
	}

	const { currencies, account } = overviews;
	return (
		<React.Fragment>
			{ currencies.map( ( overview: AccountOverview.Overview ) => (
				<DepositsInformationOverview
					key={ overview.currency }
					account={ account }
					overview={ overview }
				/>
			) ) }
		</React.Fragment>
	);
};

export default DepositsInformation;
