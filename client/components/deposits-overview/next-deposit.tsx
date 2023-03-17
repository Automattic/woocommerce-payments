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

interface NextDepositProps {
	isLoading: boolean;
}
/**
 * Renders a Deposits Overview Section Heading.
 *
 * Used in the Deposits Overview component to render Next Deposits and Deposits History section headings.
 *
 * @param {HeadingProps} props Section heading props { title, desc, isLoading (optional) }
 * @return {JSX.Element} Rendered element with deposits overview
 */
const NextDepositDetails: React.FC< NextDepositProps > = ( {
	isLoading,
}: NextDepositProps ): JSX.Element => {
	return (
		<>
			{ /* Next Deposit Heading */ }
			<div className="wcpay-deposits-overview__heading">
				<span className="wcpay-deposits-overview__heading__title">
					<Loadable
						isLoading={ isLoading }
						display="inline"
						placeholder={ strings.next_deposits.title }
						value={ strings.next_deposits.title }
					/>
				</span>

				<span className="wcpay-deposits-overview__heading__description">
					<Loadable
						isLoading={ isLoading }
						display="inline"
						placeholder={ strings.next_deposits.description }
						value={ strings.next_deposits.description }
					/>
				</span>
			</div>
			{ /* Next Deposit Table */ }
		</>
	);
};

export default NextDepositDetails;
