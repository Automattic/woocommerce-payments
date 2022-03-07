/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

interface RequirementsInfoObjectType {
	[ key: string ]: string;
}

const RequirementsInfoObject: RequirementsInfoObjectType = {
	'individual.id_number': __(
		'Personal Identification Number',
		'woocommerce-payments'
	),
	'business_profile.url': __( 'Business Website', 'woocommerce-payments' ),
	'company.tax_id': __( 'Business Number', 'woocommerce-payments' ),
};

export default RequirementsInfoObject;
