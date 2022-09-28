/** @format */

/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

interface TypeKeyMap {
	[ key: string ]: string;
}

const businessTypeStrings: TypeKeyMap = {
	individual: __( 'Individual', 'woocommerce-payments' ),
	company: __( 'Company', 'woocommerce-payments' ),
	non_profit: __( 'Non-Profit', 'woocommerce-payments' ),
	government_entity: __( 'Government Entity', 'woocommerce-payments' ),
};

export default businessTypeStrings;
