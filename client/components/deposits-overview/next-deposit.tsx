/**
 * External dependencies
 */
import * as React from 'react';
import { Flex, FlexItem, Icon } from '@wordpress/components';
import { calendar } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import strings from './strings';
import Loadable from 'components/loadable';
import { getNextDeposit } from './utils';
import DepositStatusChip from 'components/deposit-status-chip';
import { getDepositDate } from 'deposits/utils';

type NextDepositProps = {
	isLoading: boolean;
	overview: AccountOverview.Overview;
};

/**
 * Renders the Next Deposit details component.
 *
 * This component included the next deposit heading, table and notice.
 *
 * @param {NextDepositProps} props Next Deposit details props.
 * @return {JSX.Element} Rendered element with Next Deposit details.
 */
const NextDepositDetails: React.FC< NextDepositProps > = ( {
	isLoading,
	overview,
} ): JSX.Element => {
	const tableClass = 'wcpay-deposits-overview__table';
	const nextDeposit = getNextDeposit( overview );
	const nextDepositDate = getDepositDate(
		nextDeposit.date > 0 ? nextDeposit : null
	);

	return (
		<>
			{ /* Next Deposit Heading */ }
			<div className="wcpay-deposits-overview__heading">
				<span className="wcpay-deposits-overview__heading__title">
					<Loadable
						isLoading={ isLoading }
						value={ strings.nextDeposit.title }
					/>
				</span>

				<span className="wcpay-deposits-overview__heading__description">
					<Loadable
						isLoading={ isLoading }
						value={ strings.nextDeposit.description }
					/>
				</span>
			</div>
			{ /* Next Deposit Table */ }
			<div className={ tableClass }>
				<Flex className={ `${ tableClass }__row__header` }>
					<FlexItem className={ `${ tableClass }__cell` }>
						<Loadable
							isLoading={ isLoading }
							value={ strings.tableHeaders.nextDepositDate }
						/>
					</FlexItem>
					<FlexItem className={ `${ tableClass }__cell` }>
						<Loadable
							isLoading={ isLoading }
							value={ strings.tableHeaders.status }
						/>
					</FlexItem>
					<FlexItem className={ `${ tableClass }__cell` }>
						<Loadable
							isLoading={ isLoading }
							value={ strings.tableHeaders.amount }
						/>
					</FlexItem>
				</Flex>
				<Flex className={ `${ tableClass }__row` }>
					<FlexItem className={ `${ tableClass }__cell` }>
						{ ! isLoading && (
							<Icon icon={ calendar } size={ 17 } />
						) }
						<Loadable
							isLoading={ isLoading }
							placeholder="MMMM DD, YYYY"
							value={ nextDepositDate }
						/>
					</FlexItem>
					<FlexItem className={ `${ tableClass }__cell` }>
						<Loadable
							isLoading={ isLoading }
							placeholder="Estimated"
							children={
								<DepositStatusChip
									status={ nextDeposit.status }
									isCompact
								/>
							}
						/>
					</FlexItem>
					<FlexItem className={ `${ tableClass }__cell` }>
						<Loadable
							isLoading={ isLoading }
							placeholder="$00,000.00"
							value={ nextDeposit.amount }
						/>
					</FlexItem>
				</Flex>
			</div>
		</>
	);
};

export default NextDepositDetails;
