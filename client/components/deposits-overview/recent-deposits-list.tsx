/**
 * External dependencies
 */
import * as React from 'react';
import {
	CardBody,
	CardDivider,
	Flex,
	FlexItem,
	Icon,
} from '@wordpress/components';
import { calendar } from '@wordpress/icons';
import { Link } from '@woocommerce/components';
import InfoOutlineIcon from 'gridicons/dist/info-outline';
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import './style.scss';
import DepositStatusPill from 'components/deposit-status-pill';
import { getDepositDate } from 'deposits/utils';
import { CachedDeposit } from 'wcpay/types/deposits';
import { formatCurrency } from 'wcpay/utils/currency';
import { getDetailsURL } from 'wcpay/components/details-link';
import BannerNotice from '../banner-notice';

interface DepositRowProps {
	deposit: CachedDeposit;
}

interface RecentDepositsProps {
	deposits: CachedDeposit[];
}

const tableClass = 'wcpay-deposits-overview__table';

/**
 * Renders a recent deposits table row.
 *
 * @return {JSX.Element} Deposit table row.
 */
const DepositTableRow: React.FC< DepositRowProps > = ( {
	deposit,
} ): JSX.Element => {
	return (
		<Flex className={ `${ tableClass }__row` }>
			<FlexItem className={ `${ tableClass }__cell` }>
				<Icon icon={ calendar } size={ 17 } />
				<Link href={ getDetailsURL( deposit.id, 'deposits' ) }>
					{ getDepositDate( deposit ) }
				</Link>
			</FlexItem>
			<FlexItem className={ `${ tableClass }__cell` }>
				<DepositStatusPill status={ deposit.status } />
			</FlexItem>
			<FlexItem className={ `${ tableClass }__cell` }>
				{ formatCurrency( deposit.amount, deposit.currency ) }
			</FlexItem>
		</Flex>
	);
};

/**
 * Renders the Recent Deposit details component.
 *
 * This component includes the recent deposit heading, table and notice.
 *
 * @param {RecentDepositsProps} props Recent Deposit props.
 * @return {JSX.Element} Rendered element with Next Deposit details.
 */
const RecentDepositsList: React.FC< RecentDepositsProps > = ( {
	deposits,
} ): JSX.Element => {
	if ( deposits.length === 0 ) {
		return <></>;
	}

	// Add a notice indicating the potential business day delay for pending and in_transit deposits.
	// The notice is added after the oldest pending or in_transit deposit.
	const oldestPendingDepositId = [ ...deposits ]
		.reverse()
		.find(
			( deposit ) =>
				'pending' === deposit.status || 'in_transit' === deposit.status
		)?.id;
	const depositRows = deposits.map( ( deposit ) => (
		<Fragment key={ deposit.id }>
			<DepositTableRow deposit={ deposit } />
			{ deposit.id === oldestPendingDepositId && (
				<BannerNotice
					className="wcpay-deposits-overview__business-day-delay-notice"
					status="info"
					icon={ <InfoOutlineIcon /> }
					children={
						'Deposits pending or in-transit may take 1-2 business days to appear in your bank account once dispatched'
					}
					isDismissible={ false }
				/>
			) }
		</Fragment>
	) );

	return (
		<>
			{ /* Next Deposit Table */ }
			<CardBody className={ `${ tableClass }__container` }>
				<Flex className={ `${ tableClass }__row__header` }>
					<FlexItem className={ `${ tableClass }__cell` }>
						{ __( 'Dispatch date', 'woocommerce-payments' ) }
					</FlexItem>
					<FlexItem className={ `${ tableClass }__cell` }>
						{ __( 'Status', 'woocommerce-payments' ) }
					</FlexItem>
					<FlexItem className={ `${ tableClass }__cell` }>
						{ __( 'Amount', 'woocommerce-payments' ) }
					</FlexItem>
				</Flex>
			</CardBody>
			<CardDivider />
			<CardBody className={ `${ tableClass }__container` }>
				{ depositRows }
			</CardBody>
		</>
	);
};

export default RecentDepositsList;
