/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Pill from '../../../pill';

const NewPill = () => {
	return (
		<Pill className={ 'discoverability-card__new-feature-pill' }>
			{ __( 'New', 'woocommerce-payments' ) }
		</Pill>
	);
};

export default NewPill;
