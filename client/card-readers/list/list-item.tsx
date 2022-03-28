/** @format */
/**
 * External dependencies
 */
import classNames from 'classnames';
import { __ } from '@wordpress/i18n';
import React from 'react';

/*eslint-disable camelcase*/
const CardReaderListItem: React.FunctionComponent< {
	reader: {
		id: string;
		device_type: string;
		is_active: boolean;
	};
} > = ( {
	reader: { id, device_type: deviceType, is_active: isActive },
} ): JSX.Element => {
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
