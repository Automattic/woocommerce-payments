/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { CollapsibleList } from '@woocommerce/experimental';

/**
 * Internal dependencies.
 */
import './style.scss';

const TaskList = () => {
	return (
		<CollapsibleList
			collapsed={ false }
			collapseLabel={ __( 'Hide tasks', 'woocommerce-payments' ) }
			expandLabel={ __( 'Show tasks', 'woocommerce-payments' ) }
		/>
	);
};

export default TaskList;
