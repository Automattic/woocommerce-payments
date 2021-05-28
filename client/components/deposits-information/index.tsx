/* eslint-disable wpcalypso/jsx-classname-namespace */
/* eslint-disable no-unused-vars */
/* eslint-disable camelcase */
/** @format **/

/**
 * External dependencies
 */
import * as React from 'react';
import {
	Card,
	CardHeader,
	CardBody,
	Flex,
	FlexBlock,
} from '@wordpress/components';
import Gridicon from 'gridicons';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useAllDeposistsOverviews } from 'data';
import Loadable from 'components/loadable';
import { getDetailsURL } from 'components/details-link';
import {
	getBalanceDepositCount,
	getDepositScheduleDescriptor,
	getDepositDate,
	getNextDepositLabelFormatted,
} from 'deposits/overview';
import { formatCurrency, formatCurrencyName } from 'utils/currency';
import './style.scss';

const DepositsInformationLoading = (): any => {
	return (
		<Card>
			<CardHeader className="wcpay-deposits-information-header">
				{ /* This div will be used for a proper layout next to the button. */ }
				<div>
					<h3 className="wcpay-deposits-information-header__title">
						{ __( 'Deposits overview', 'woocommerce-payments' ) }
					</h3>

					<p className="wcpay-deposits-information-header__schedule">
						<Gridicon
							icon="calendar"
							size={ 24 }
							className="wcpay-deposits-information-header__icon"
						/>
						<Loadable
							isLoading={ true }
							display="inline"
							placeholder="Deposit schedule here"
						/>
					</p>
				</div>
			</CardHeader>
			<CardBody>
				<Loadable
					isLoading={ true }
					display="inline"
					placeholder="Deposit information placeholder"
				/>
			</CardBody>
		</Card>
	);
};

type DepositsInformationBlockProps = {
	title: string;
	value: string;
	children?: any;
};

const DepositsInformationBlock: React.FunctionComponent< DepositsInformationBlockProps > = ( {
	title,
	value,
	children,
} ) => {
	return (
		<FlexBlock className="wcpay-deposits-information-block">
			<div className="wcpay-deposits-information-block__title">
				{ title }
			</div>
			<div className="wcpay-deposits-information-block__value">
				{ value }
			</div>
			<div className="wcpay-deposits-information-block__extra">
				{ children }
			</div>
		</FlexBlock>
	);
};

type OverviewProps = {
	overview: AccountOverview.Overview;
	account: AccountOverview.Account;
};

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
	const { currency, pending, nextScheduled, lastPaid, available } = overview;

	const pendingAmount = pending ? pending.amount : 0;
	const pendingDepositsLink = pending.deposits_count && (
		<a href={ getDetailsURL( nextScheduled.id, 'deposits' ) }>
			{ getBalanceDepositCount( pending ) }
		</a>
	);

	const nextScheduledAmount = nextScheduled ? nextScheduled.amount : 0;
	const nextScheduledLink = nextScheduled && (
		<a href={ getDetailsURL( nextScheduled.id, 'deposits' ) }>
			{ getNextDepositLabelFormatted( nextScheduled ) }
		</a>
	);

	const lastPaidAmount = lastPaid ? lastPaid.amount : 0;
	const lastPaidLink = lastPaid && (
		<a href={ getDetailsURL( lastPaid.id, 'deposits' ) }>
			{ getDepositDate( lastPaid ) }
		</a>
	);

	const availableAmount = available ? available.amount : 0;

	const scheduleDescriptor = getDepositScheduleDescriptor( {
		account,
		last_deposit: lastPaid,
	} );

	return (
		<Card>
			<CardHeader className="wcpay-deposits-information-header">
				{ /* This div will be used for a proper layout next to the button. */ }
				<div>
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

const DepositsInformation = (): JSX.Element | Array< JSX.Element > => {
	const { overviews, isLoading } = useAllDeposistsOverviews();

	if ( isLoading ) {
		return <DepositsInformationLoading />;
	}

	const { currencies, account } = overviews;
	return currencies.map( ( overview: AccountOverview.Overview ) => (
		<DepositsInformationOverview
			key={ overview.currency }
			account={ account }
			overview={ overview }
		/>
	) );
};

export default DepositsInformation;
