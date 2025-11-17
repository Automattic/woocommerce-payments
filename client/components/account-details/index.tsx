/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { Card, CardBody, CardHeader } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import {
	AccountDetailsType,
	AccountDetailsData,
} from 'wcpay/types/account/account-details';
import { AccountTools } from 'wcpay/components/account-status/account-tools';
import AccountFees from 'wcpay/components/account-status/account-fees';
import Banner from './banner';
import PayoutStatusWrapper from './payout-status-wrapper';
import HeaderTitle from './header-title';

interface AccountDetailsProps {
	accountDetails: AccountDetailsType;
	accountFees?: any[];
	accountLink?: string;
}

const AccountDetailsCard: React.FC< {
	title: React.ReactNode;
	children: React.ReactNode;
} > = ( { title, children } ) => {
	return (
		<Card size="medium">
			<CardHeader className="woopayments-account-details__header">
				{ title }
			</CardHeader>
			<CardBody>{ children }</CardBody>
		</Card>
	);
};

const AccountDetailsError: React.FC = () => {
	const cardTitle = __( 'Account details', 'woocommerce-payments' );
	return (
		<AccountDetailsCard title={ cardTitle }>
			{ __( 'Error loading account details.', 'woocommerce-payments' ) }
		</AccountDetailsCard>
	);
};

const AccountDetailsContent: React.FC< {
	accountDetails: AccountDetailsData;
	accountFees: any[];
	accountLink?: string;
} > = ( { accountDetails, accountFees, accountLink } ) => {
	const processedAccountLink = accountLink
		? addQueryArgs( accountLink, {
				from: 'WCPAY_ACCOUNT_DETAILS',
				source: 'wcpay-account-details',
		  } )
		: null;

	const cardTitle = (
		<>
			<HeaderTitle
				accountStatus={ accountDetails.account_status }
				accountLink={ processedAccountLink }
			/>
			<Banner banner={ accountDetails.banner } />
		</>
	);

	return (
		<AccountDetailsCard title={ cardTitle }>
			<PayoutStatusWrapper
				payoutStatus={ accountDetails.payout_status }
			/>

			<AccountTools />

			{ accountFees && accountFees.length > 0 && (
				<AccountFees accountFees={ accountFees } />
			) }
		</AccountDetailsCard>
	);
};

const AccountDetails: React.FC< AccountDetailsProps > = ( {
	accountDetails,
	accountFees = [],
	accountLink,
} ) => {
	return null === accountDetails ? (
		<AccountDetailsError />
	) : (
		<AccountDetailsContent
			accountDetails={ accountDetails }
			accountFees={ accountFees }
			accountLink={ accountLink }
		/>
	);
};

export default AccountDetails;
