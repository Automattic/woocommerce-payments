/** @format **/

/**
 * External dependencies
 */

import React from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */

import './style.scss';
import CardNotice from 'wcpay/components/card-notice';
import Loadable from 'wcpay/components/loadable';
import { Charge } from 'wcpay/types/charges';

interface MissingOrderNoticeProps {
	charge: Charge;
	isLoading: boolean;
	onButtonClick: () => void;
}

const MissingOrderNotice: React.FC< MissingOrderNoticeProps > = ( {
	charge,
	isLoading,
	onButtonClick,
} ) => {
	return (
		<>
			<Loadable isLoading={ isLoading } placeholder="">
				<CardNotice
					actions={
						! charge.refunded ? (
							<Button
								variant="primary"
								isSmall={ false }
								onClick={ onButtonClick }
							>
								{ __( 'Refund', 'woocommerce-payments' ) }
							</Button>
						) : (
							<></>
						)
					}
				>
					{ __(
						'This transaction is not connected to order. ',
						'woocommerce-payments'
					) }
					{ charge.refunded
						? __(
								'It has been refunded and is not a subject for disputes.',
								'woocommerce-payments'
						  )
						: __(
								'Investigate this purchase and refund the transaction as needed.',
								'woocommerce-payments'
						  ) }
				</CardNotice>
			</Loadable>
		</>
	);
};

export default MissingOrderNotice;
