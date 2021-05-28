/**
 * External dependencies
 */
import { SummaryList, SummaryNumber } from '@woocommerce/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import wcpayTracks from 'tracks';

const EmptyStateDepositsOverview = () => {
	const handleSummaryInteraction = () => {
		wcpayTracks.recordEvent(
			wcpayTracks.events.DEPOSITS_SUMMARY_EMPTY_STATE_CLICKED
		);
	};

	return (
		<div
			className="wcpay-deposits-overview"
			onClick={ handleSummaryInteraction }
			onKeyDown={ handleSummaryInteraction }
			role="button"
			tabIndex="0"
		>
			<SummaryList
				label={ __( 'Deposits overview', 'woocommerce-payments' ) }
			>
				{ () => {
					return [
						<SummaryNumber
							key="lastDeposit"
							label={ __(
								'Last deposit',
								'woocommerce-payments'
							) }
							value="-"
						/>,
						<SummaryNumber
							key="nextDeposit"
							label={ __(
								'Next deposit',
								'woocommerce-payments'
							) }
							value="-"
						/>,
						<SummaryNumber
							key="pendingBalance"
							label={ __(
								'Pending balance',
								'woocommerce-payments'
							) }
							value="-"
						/>,
						<SummaryNumber
							key="availableBalance"
							label={ __(
								'Available balance',
								'woocommerce-payments'
							) }
							value="-"
						/>,
					];
				} }
			</SummaryList>
		</div>
	);
};

export default EmptyStateDepositsOverview;
