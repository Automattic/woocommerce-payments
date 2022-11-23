/** @format */
/**
 * External dependencies
 */
import classNames from 'classnames';
import { __ } from '@wordpress/i18n';
import React from 'react';
import { CardReader } from 'wcpay/types/card-readers';

/*eslint-disable camelcase*/
const CardReaderListItem: React.FunctionComponent< {
	reader: CardReader;
} > = ( { reader } ): JSX.Element => {
	const { id, device_type: deviceType, is_active: isActive } = reader;

	const status = isActive
		? __( 'Active', 'woocomerce-payments' )
		: __( 'Inactive', 'woocomerce-payments' );

	return (
		<li className={ classNames( 'card-readers-item', id ) }>
			<div className="card-readers-item__id">
				<span>{ id }</span>
			</div>
			<div className="card-readers-item__type">
				<span>{ deviceType }</span>
			</div>
			<div className="card-readers-item__status">
				<span className={ isActive ? 'active' : 'inactive' }>
					{ status }
				</span>
			</div>
		</li>
	);
};

export default CardReaderListItem;
