/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { SummaryList, SummaryNumber } from '@woocommerce/components';
import Gridicon from 'gridicons';

/**
 * Internal dependencies
 */
import './style.scss';

const DepositsOverview = () => {
	return (
		<div className="wcpay-deposits-overview">
			<p className="wcpay-deposits-overview__schedule">
				<Gridicon icon="calendar" className="wcpay-deposits-overview__schedule-icon" />
				<span className="wcpay-deposits-overview__schedule-label">{ __( 'Deposit Schedule:', 'woocommerce-payments' ) }</span>
				{ ' ' }
				<span className="wcpay-deposits-overview__schedule-value">Automatic, every business day</span>
			</p>
			<SummaryList label={ __( 'Deposits Overview', 'woocommerce-payments' ) }>
				{ () => {
					return [
						<SummaryNumber
							key="lastDeposit"
							value={ '$65.74' }
							label={ __( 'Last deposit', 'woocommerce-payments' ) }
							prevLabel="April 18, 2019"
						/>,
						<SummaryNumber
							key="nextDeposit"
							value={ '$15.22' }
							label={ __( 'Next deposit', 'woocommerce-payments' ) }
							prevLabel="Est. April 18, 2019 - In transit"
						/>,
						<SummaryNumber
							key="pendingBalance"
							value={ '$48.66' }
							label={ __( 'Pending balance', 'woocommerce-payments' ) }
							prevLabel="2 deposits"
						/>,
						<SummaryNumber
							key="availableBalance"
							value={ '$0.00' }
							label={ __( 'Available balance', 'woocommerce-payments' ) }
							prevLabel=""
						/>,
					];
				} }
			</SummaryList>
		</div>
	);
};

export default DepositsOverview;
