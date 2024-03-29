/**
 * External dependencies
 */
import React from 'react';
import { Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import {
	AffirmIcon,
	KlarnaIcon,
	BancontactIcon,
	GiropayIcon,
} from 'wcpay/payment-methods-icons';

const Duplicates = () => {
	return (
		<Card>
			<CardBody>
				<div>
					<div>
						<div
							style={ {
								display: 'flex',
								justifyContent: 'start', // This will align the items to the start of the container
								alignItems: 'center', // This will center the items vertically
								gap: '10px', // Adjust the space between items as needed
							} }
						>
							<KlarnaIcon className="payment-method__icon-wrapper" />
							<AffirmIcon className="payment-method__icon-wrapper" />
							<BancontactIcon className="payment-method__icon-wrapper" />
							<GiropayIcon className="payment-method__icon-wrapper" />
							{ /* Add more icons as needed */ }
						</div>
					</div>
				</div>
			</CardBody>
		</Card>
	);
};

export default Duplicates;
