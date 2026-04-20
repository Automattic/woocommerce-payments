/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { getHistory } from '@woocommerce/navigation';

/**
 * Internal dependencies.
 */
import { Spinner, Icon, Flex, FlexItem } from '@wordpress/components';
import Page from 'components/page';
import { useDispute } from 'data/index';
import { getAdminUrl } from 'wcpay/utils';

import './style.scss';
import warning from 'wcpay/components/icons/warning';

const RedirectToTransactionDetails: React.FC< { query: { id: string } } > = ( {
	query: { id: disputeId },
} ) => {
	const { dispute, error, isLoading } = useDispute( disputeId );

	useEffect( () => {
		if ( ! isLoading && dispute?.charge ) {
			const transactionDetailsUrl = getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/transactions/details',
				id: dispute.payment_intent,
				transaction_id: dispute.charge.balance_transaction,
				type: 'dispute',
			} );
			getHistory().replace( transactionDetailsUrl );
		}
	}, [ dispute, isLoading ] );

	return (
		<Page>
			<Flex
				direction="column"
				className="wcpay-dispute-detail-legacy-redirect"
			>
				{ error ? (
					<>
						<FlexItem>
							<Icon icon={ warning } type="warning" size={ 32 } />
						</FlexItem>
						<FlexItem>
							<div>
								<b>
									{ __(
										'Error retrieving dispute',
										'woocommerce-payments'
									) }
								</b>
							</div>
							<div>
								{ __(
									'Please check your network and try again.',
									'woocommerce-payments'
								) }
							</div>
						</FlexItem>
					</>
				) : (
					<>
						<FlexItem>
							<Spinner />
						</FlexItem>
						<FlexItem>
							<div>
								<b>
									{ __(
										'One moment please',
										'woocommerce-payments'
									) }
								</b>
							</div>
							<div>
								{ __(
									'Redirecting to payment details…',
									'woocommerce-payments'
								) }
							</div>
						</FlexItem>
					</>
				) }
			</Flex>
		</Page>
	);
};

export default RedirectToTransactionDetails;
