/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import {
	Card,
	CardBody,
	CardFooter,
	ExternalLink,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import RecommendedApms from './list';
import strings from '../strings';
import TipBox from 'components/tip-box';
import Chip from 'components/chip';
import WooPaymentsIcon from 'assets/images/payment-methods/woopayments.svg?asset';

const Image = () => <img src={ WooPaymentsIcon } alt="" />;

const WooPaymentsDetails = () => (
	<Card
		size="large"
		className="connect-account-page__unsupported-country-details"
	>
		<CardBody>
			<div className="connect-account-page__unsupported-country-details--wrapper">
				<div className="connect-account-page__unsupported-country-details--image">
					<Image />
				</div>
				<div className="connect-account-page__unsupported-country-details--content">
					<h3>
						{ strings.nonSupportedCountry.title }
						<Chip
							message={
								strings.nonSupportedCountry.not_available
							}
							type="light"
						/>
					</h3>
					<span>{ strings.nonSupportedCountry.description }</span>
				</div>
			</div>
			<TipBox color="yellow">
				{ strings.nonSupportedCountry.notice }
			</TipBox>
		</CardBody>
		<CardFooter>
			<ExternalLink href="https://woocommerce.com/product-category/woocommerce-extensions/payment-gateways/wallets/?categoryIds=28682&collections=product&page=1">
				{ strings.nonSupportedCountry.footer }
			</ExternalLink>
		</CardFooter>
	</Card>
);

const ConnectUnsupportedAccountPage: React.FC = () => {
	return (
		<Card className="connect-account-page__unsupported-country">
			<h2>{ strings.nonSupportedCountry.heading }</h2>
			<RecommendedApms />
			<WooPaymentsDetails />
		</Card>
	);
};

export default ConnectUnsupportedAccountPage;
