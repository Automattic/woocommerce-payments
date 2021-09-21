/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardHeader, CardBody } from '@wordpress/components';
import Gridicon from 'gridicons';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Loadable from 'components/loadable';

const DepositsInformationLoading = (): JSX.Element => {
	return (
		<Card>
			<CardHeader
				size="small"
				className="wcpay-deposits-information-header"
			>
				{ /* This div will be used for a proper layout next to the button. */ }
				<div className="wcpay-deposits-information-header__heading">
					<h3 className="wcpay-deposits-information-header__title">
						{ __( 'Deposits overview', 'woocommerce-payments' ) }
					</h3>

					<p className="wcpay-deposits-information-header__schedule">
						<Gridicon
							icon="calendar"
							size={ 24 }
							className="wcpay-deposits-information-header__icon"
						/>
						<Loadable
							isLoading={ true }
							display="inline"
							placeholder="Deposit schedule here"
						/>
					</p>
				</div>
			</CardHeader>
			<CardBody>
				<Loadable
					isLoading={ true }
					display="inline"
					placeholder="Deposit information placeholder"
				/>
			</CardBody>
		</Card>
	);
};

export default DepositsInformationLoading;
