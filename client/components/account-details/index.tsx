/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardHeader, FlexItem } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import {
	AccountDetailsType,
	AccountDetailsData,
} from 'wcpay/types/account/account-details';

interface AccountDetailsCardProps {
	title: React.ReactNode;
	children?: React.ReactNode;
	value?: React.ReactNode;
}

const AccountDetailsCard = ( props: AccountDetailsCardProps ) => {
	const { title, children, value } = props;
	return (
		<Card size="medium">
			<CardHeader className="woocommerce-account-details__header">
				{ title }
			</CardHeader>
			<CardBody>{ children || value || null }</CardBody>
		</Card>
	);
};

const AccountDetailsError = () => {
	const cardTitle = __( 'Account details', 'woocommerce-payments' );
	return (
		<AccountDetailsCard title={ cardTitle }>
			{ __( 'Error loading account details.', 'woocommerce-payments' ) }
		</AccountDetailsCard>
	);
};

const AccountDetailsContent = ( {
	accountDetails,
}: {
	accountDetails: AccountDetailsData;
} ) => {
	const cardTitle = (
		<>
			<FlexItem className={ 'account-details-title' }>
				{ __( 'Account details', 'woocommerce-payments' ) }
			</FlexItem>
		</>
	);

	return (
		<AccountDetailsCard title={ cardTitle }>
			<pre>{ JSON.stringify( accountDetails, null, 2 ) }</pre>
		</AccountDetailsCard>
	);
};

const AccountDetails = ( {
	accountDetails,
}: {
	accountDetails: AccountDetailsType;
} ) => {
	return null === accountDetails ? (
		<AccountDetailsError />
	) : (
		<AccountDetailsContent accountDetails={ accountDetails } />
	);
};

export default AccountDetails;
