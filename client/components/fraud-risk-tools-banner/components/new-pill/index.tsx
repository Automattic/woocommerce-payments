/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Pill from '../../../pill';

const NewPill: React.FC = () => {
	return (
		<Pill className={ 'discoverability-card__new-feature-pill' }>
			{ __( 'New', 'woocommerce-payments' ) }
		</Pill>
	);
};

export default NewPill;
