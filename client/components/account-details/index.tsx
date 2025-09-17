/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
	Flex,
	FlexBlock,
	FlexItem,
} from '@wordpress/components';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { published, caution, error, info } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import './style.scss';
import {
	AccountDetailsType,
	AccountDetailsData,
} from 'wcpay/types/account/account-details';
import Chip, { ChipType } from 'wcpay/components/chip';
import InlineNotice from 'wcpay/components/inline-notice';
import { ClickTooltip } from 'wcpay/components/tooltip';
import { AccountTools } from 'wcpay/components/account-status/account-tools';
import AccountFees from 'wcpay/components/account-status/account-fees';
import { recordEvent } from 'wcpay/tracks';

interface AccountDetailsProps {
	accountDetails: AccountDetailsType;
	accountFees?: any[];
	accountLink?: string;
}

interface StatusItemProps {
	label: string;
	align?: 'center' | 'top' | 'bottom';
	children: React.ReactNode;
}

const PayoutStatusItem: React.FC< StatusItemProps > = ( {
	label,
	align = 'center',
	children,
} ) => {
	return (
		<Flex
			direction="row"
			align={ align }
			justify="left"
			gap={ 3 }
			className="woopayments-account-details__payout-status-item"
		>
			<FlexItem className="item-label">{ label }</FlexItem>
			<FlexBlock className="item-value">{ children }</FlexBlock>
		</Flex>
	);
};

const getChipTypeFromColor = (
	color: 'green' | 'yellow' | 'red'
): ChipType => {
	switch ( color ) {
		case 'green':
			return 'success';
		case 'yellow':
			return 'warning';
		case 'red':
			return 'alert';
		default:
			return 'primary';
	}
};

const getNoticeStatusFromColor = ( color: 'yellow' | 'red' ) => {
	return color === 'yellow' ? 'warning' : 'error';
};

const iconMap = {
	published: published,
	caution: caution,
	error: error,
	info: info,
};
const PayoutStatus: React.FC< {
	payoutStatus: AccountDetailsData[ 'payout_status' ];
} > = ( { payoutStatus } ) => {
	const chipType = getChipTypeFromColor( payoutStatus.background_color );

	return (
		<Flex align="center" gap={ 0 } justify="flex-start">
			<Chip
				type={ chipType }
				className={ `payout-status-chip payout-status-chip--${ payoutStatus.background_color }` }
				message={ payoutStatus.text }
				icon={ iconMap[ payoutStatus.icon ] ?? 'info' }
			/>
			{ payoutStatus.popover && (
				<ClickTooltip
					buttonIcon={ <HelpOutlineIcon /> }
					buttonLabel={ __(
						'More information about payout status',
						'woocommerce-payments'
					) }
					buttonSize={ 24 }
					maxWidth={ '300px' }
					content={
						<div>
							{ payoutStatus.popover.text }
							{ payoutStatus.popover.cta_text &&
								payoutStatus.popover.cta_link && (
									<>
										{ ' ' }
										<ExternalLink
											href={
												payoutStatus.popover.cta_link
											}
										>
											{ payoutStatus.popover.cta_text }
										</ExternalLink>
									</>
								) }
						</div>
					}
				/>
			) }
		</Flex>
	);
};

const AccountDetailsCard: React.FC< {
	title: React.ReactNode;
	children: React.ReactNode;
} > = ( { title, children } ) => {
	return (
		<Card size="medium">
			<CardHeader className="woopayments-account-details__header">
				<Flex
					direction="row"
					align="center"
					justify="left"
					gap={ 3 }
					expanded
				>
					{ title }
				</Flex>
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
			<FlexItem className="account-details">
				{ __( 'Account details', 'woocommerce-payments' ) }
			</FlexItem>
			<FlexBlock className="account-status">
				<Chip
					message={ accountDetails.account_status.text }
					type={ getChipTypeFromColor(
						accountDetails.account_status.background_color
					) }
				/>
			</FlexBlock>
			{ processedAccountLink && (
				<FlexItem className="edit-details">
					<Button
						variant="link"
						onClick={ () =>
							recordEvent( 'wcpay_account_details_link_clicked', {
								from: 'WCPAY_ACCOUNT_DETAILS',
								source: 'wcpay-account-details',
							} )
						}
						href={ processedAccountLink }
						target="_blank"
						__next40pxDefaultSize
					>
						{ __( 'Edit details', 'woocommerce-payments' ) }
					</Button>
				</FlexItem>
			) }
		</>
	);

	return (
		<AccountDetailsCard title={ cardTitle }>
			{ accountDetails.banner && (
				<InlineNotice
					status={ getNoticeStatusFromColor(
						accountDetails.banner.background_color
					) }
					icon={ iconMap[ accountDetails.banner.icon ?? 'info' ] }
					className="woopayments-account-details__banner"
					isDismissible={ false }
				>
					<div>
						{ accountDetails.banner.text }
						{ accountDetails.banner.cta_text &&
							accountDetails.banner.cta_link && (
								<>
									{ ' ' }
									<ExternalLink
										href={ accountDetails.banner.cta_link }
									>
										{ accountDetails.banner.cta_text }
									</ExternalLink>
								</>
							) }
					</div>
				</InlineNotice>
			) }

			<PayoutStatusItem
				label={ __( 'Payouts:', 'woocommerce-payments' ) }
			>
				<PayoutStatus payoutStatus={ accountDetails.payout_status } />
			</PayoutStatusItem>

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
