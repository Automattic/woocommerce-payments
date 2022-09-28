/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const groups = {
	personalDetails: __( 'Personal details', 'woocommerce-payments' ),
	taxInfo: __( 'Tax information', 'woocommerce-payments' ),
	companyInfo: __( 'Company information', 'woocommerce-payments' ),
	company: __( 'Company', 'woocommerce-payments' ),
	owner: __( 'owner', 'woocommerce-payments' ),
	director: __( 'director', 'woocommerce-payments' ),
	executive: __( 'executive', 'woocommerce-payments' ),
	representativeDetails: __(
		'Representative details',
		'woocommerce-payments'
	),
	bankDetails: __( 'Bank account details', 'woocommerce-payments' ),
	companyBank: __( 'Company bank account details', 'woocommerce-payments' ),
};

export const requirements = {
	name: __( 'name', 'woocommerce-payments' ),
	dob: __( 'date of birth', 'woocommerce-payments' ),
	address: __( 'address', 'woocommerce-payments' ),
	email: __( 'email', 'woocommerce-payments' ),
	phone: __( 'phone', 'woocommerce-payments' ),
	nationality: __( 'nationality', 'woocommerce-payments' ),
	ssn: __( 'Social Security Number (SSN)', 'woocommerce-payments' ),
	taxId: __( 'Company Tax ID', 'woocommerce-payments' ),
	registrationNumber: __(
		'Company Registration Number',
		'woocommerce-payments'
	),
};
