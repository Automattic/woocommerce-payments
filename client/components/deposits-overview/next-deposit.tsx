/**
 * External dependencies
 */
import * as React from 'react';
import { Flex, FlexItem, Icon } from '@wordpress/components';
import { calendar } from '@wordpress/icons';
import InfoOutlineIcon from 'gridicons/dist/info-outline';
import interpolateComponents from '@automattic/interpolate-components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import strings from './strings';
import Loadable from 'components/loadable';
import { getNextDeposit } from './utils';
import DepositStatusPill from 'components/deposit-status-pill';
import { getDepositDate } from 'deposits/utils';
import { useDepositIncludesLoan } from 'wcpay/data';
import BannerNotice from 'wcpay/components/banner-notice';

type NextDepositProps = {
	isLoading: boolean;
	overview?: AccountOverview.Overview;
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
	const { includesFinancingPayout } = useDepositIncludesLoan(
		nextDeposit.id
	);

	return (
		<>
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
								<DepositStatusPill
									status={ nextDeposit.status }
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
			{ /* Deposit includes capital funds notice */ }
			{ ! isLoading && includesFinancingPayout && (
				<div className="wcpay-deposits-overview__notices">
					<BannerNotice
						status="warning"
						icon={ <InfoOutlineIcon /> }
						isDismissible={ false }
					>
						{ interpolateComponents( {
							mixedString:
								strings.notices.depositIncludesLoan +
								__(
									' {{learnMoreLink}}Learn more{{/learnMoreLink}}',
									'woocommerce-payments'
								),
							components: {
								learnMoreLink: (
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a
										href={
											strings.documentationUrls.capital
										}
										target="_blank"
										rel="noreferrer"
									/>
								),
							},
						} ) }
					</BannerNotice>
				</div>
			) }
		</>
	);
};

export default NextDepositDetails;
