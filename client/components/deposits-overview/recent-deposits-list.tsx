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
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import './style.scss';
import DepositStatusChip from 'components/deposit-status-chip';
import { getDepositDate } from 'deposits/utils';
import { CachedDeposit } from 'wcpay/types/deposits';
import { formatCurrency } from 'wcpay/utils/currency';
import { getDetailsURL } from 'wcpay/components/details-link';

interface DepositRowProps {
	deposit: CachedDeposit;
}

interface RecentDepositsProps {
	deposits: CachedDeposit[];
}

const tableClass = 'wcpay-deposits-overview__table';

/**
 * Renders a recent deposits table row.
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
				<DepositStatusChip status={ deposit.status } />
			</FlexItem>
			<FlexItem className={ `${ tableClass }__cell` }>
				{ formatCurrency( deposit.amount, deposit.currency ) }
			</FlexItem>
		</Flex>
	);
};

/**
 * Renders the Recent Deposit list component.
 *
 * This component includes the recent deposit heading, table and notice.
 */
const RecentDepositsList: React.FC< RecentDepositsProps > = ( {
	deposits,
} ): JSX.Element => {
	if ( deposits.length === 0 ) {
		return <></>;
	}

	const depositRows = deposits.map( ( deposit ) => (
		<Fragment key={ deposit.id }>
			<DepositTableRow deposit={ deposit } />
		</Fragment>
	) );

	return (
		<>
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
