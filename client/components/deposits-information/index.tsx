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
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { useDepositsOverview } from 'data';
import Loadable from 'components/loadable';
import './style.scss';

const DepositsInformationLoading = (): any => {
	return (
		<Card>
			<CardHeader>Deposits overview</CardHeader>
			<CardBody>
				<Loadable
					isLoading={ true }
					display="inline"
					placeholder="Deposit information placeholder"
				>
					{ 'foo' }
				</Loadable>
			</CardBody>
		</Card>
	);
};

type DepositsInformationBlockProps = {
	title: string;
	value: string;
	children?: string;
};

const DepositsInformationBlock: React.FunctionComponent< DepositsInformationBlockProps > = ( {
	title,
	value,
	children,
} ) => {
	return (
		<FlexBlock className={ 'woocommerce-deposits-information-block' }>
			<div className={ 'woocommerce-deposits-information-block__title' }>
				{ title }
			</div>
			<div className={ 'woocommerce-deposits-information-block__value' }>
				{ value }
			</div>
			<div className={ 'woocommerce-deposits-information-block__extra' }>
				{ children }
			</div>
		</FlexBlock>
	);
};

type OverviewProps = {
	overview: AccountOverview.Overview;
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
	const { overview }: OverviewProps = props;
	return (
		<Card>
			<CardHeader>{ 'foo' }</CardHeader>
			<Flex className={ 'woocommerce-deposits-information-row' }>
				<DepositsInformationBlock
					title={ __( 'Pending balance', 'woocommerce-payments' ) }
					value={ '$ 0.00' }
				/>
				<DepositsInformationBlock
					title={ __( 'Next deposit', 'woocommerce-payments' ) }
					value={ '$ 0.00' }
				/>
			</Flex>
			<Flex className={ 'woocommerce-deposits-information-row' }>
				<DepositsInformationBlock
					title={ __( 'Last deposit', 'woocommerce-payments' ) }
					value={ '$ 0.00' }
				/>
				<DepositsInformationBlock
					title={ __( 'Available balance', 'woocommerce-payments' ) }
					value={ '$ 0.00' }
				/>
			</Flex>
		</Card>
	);
};

const DepositsInformation = (): JSX.Element => {
	const {
		overview,
		isLoading,
	}: AccountOverview.OverviewResponse = useDepositsOverview();

	return isLoading ? (
		<DepositsInformationLoading />
	) : (
		<DepositsInformationOverview overview={ overview } />
	);
};

export default DepositsInformation;
