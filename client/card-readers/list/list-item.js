/** @format */
/**
 * External dependencies
 */
import classNames from 'classnames';
import { __ } from '@wordpress/i18n';

/*eslint-disable camelcase*/
const CardReaderListItem = ( { reader: { id, device_type, is_active } } ) => {
	const status = is_active
		? __( 'Active', 'woocomerce-payments' )
		: __( 'Inactive', 'woocomerce-payments' );

	return (
		<li className={ classNames( 'card-readers-item', id ) }>
			<div className="card-readers-item__id">
				<span>{ id }</span>
			</div>
			<div className="card-readers-item__type">
				<span>{ device_type }</span>
			</div>
			<div className="card-readers-item__status">
				<span className={ is_active ? 'active' : 'inactive' }>
					{ status }
				</span>
			</div>
		</li>
	);
};

export default CardReaderListItem;
