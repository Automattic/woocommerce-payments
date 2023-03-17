/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies.
 */
import strings from './strings';
import './style.scss';
import Loadable from 'components/loadable';
import { getNextDeposit } from './utils';
import DepositStatusChip from 'components/deposit-status-chip';
import { getDepositDate } from 'deposits/utils';

interface NextDepositProps {
	isLoading: boolean;
	overview?: AccountOverview.Overview | undefined;
}

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
}: NextDepositProps ): JSX.Element => {
	return (
		<>
			{ /* Next Deposit Heading */ }
			<div className="wcpay-deposits-overview__heading">
				<span className="wcpay-deposits-overview__heading__title">
					<Loadable
						isLoading={ isLoading }
						value={ strings.next_deposits.title }
					/>
				</span>

				<span className="wcpay-deposits-overview__heading__description">
					<Loadable
						isLoading={ isLoading }
						value={ strings.next_deposits.description }
					/>
				</span>
			</div>
			{ /* Next Deposit Table */ }
		</>
	);
};

export default NextDepositDetails;
