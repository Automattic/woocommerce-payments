/** @format */

/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

interface StructureKeyMap {
	[ key: string ]: {
		[ key: string ]: string;
	};
}

const businessStructureStrings: StructureKeyMap = {
	AE: {
		llc: __( 'LLC', 'woocommerce-payments' ),
		sole_establishment: __( 'Sole establishment', 'woocommerce-payments' ),
		free_zone_llc: __( 'Free-zone LLC', 'woocommerce-payments' ),
		free_zone_establishment: __(
			'Free-zone establishment',
			'woocommerce-payments'
		),
	},
	AU: {
		public_corporation: __( 'Public company', 'woocommerce-payments' ),
		private_corporation: __(
			'Proprietary company',
			'woocommerce-payments'
		),
		sole_proprietorship: __( 'Sole trader', 'woocommerce-payments' ),
		private_partnership: __( 'Partnership', 'woocommerce-payments' ),
		unincorporated_association: __(
			'Unincorporated association',
			'woocommerce-payments'
		),
	},
	HK: {
		sole_proprietorship: __(
			'Sole proprietorship',
			'woocommerce-payments'
		),
		private_company: __( 'Private company', 'woocommerce-payments' ),
		private_partnership: __(
			'Private partnership',
			'woocommerce-payments'
		),
		incorporated_non_profit: __(
			'Incorporated non-profit',
			'woocommerce-payments'
		),
		unincorporated_non_profit: __(
			'Unincorporated non-profit',
			'woocommerce-payments'
		),
	},
	NZ: {
		public_corporation: __( 'Public corporation', 'woocommerce-payments' ),
		private_corporation: __( 'Corporation', 'woocommerce-payments' ),
		sole_proprietorship: __( 'Sole trader', 'woocommerce-payments' ),
		private_partnership: __( 'Partnership', 'woocommerce-payments' ),
		incorporated_non_profit: __(
			'Incorporated non-profit',
			'woocommerce-payments'
		),
		unincorporated_non_profit: __(
			'Unincorporated non-profit',
			'woocommerce-payments'
		),
	},
	SG: {
		sole_proprietorship: __(
			'Sole proprietorship',
			'woocommerce-payments'
		),
		private_company: __( 'Company', 'woocommerce-payments' ),
		public_company: __( 'Public company', 'woocommerce-payments' ),
		private_partnership: __( 'Partnership', 'woocommerce-payments' ),
	},
	US: {
		sole_proprietorship: __(
			'Sole proprietorship',
			'woocommerce-payments'
		),
		single_member_llc: __( 'Single-member LLC', 'woocommerce-payments' ),
		multi_member_llc: __( 'Multi-member LLC', 'woocommerce-payments' ),
		private_partnership: __(
			'Private partnership',
			'woocommerce-payments'
		),
		private_corporation: __(
			'Private corporation',
			'woocommerce-payments'
		),
		unincorporated_association: __(
			'Unincorporated association',
			'woocommerce-payments'
		),
		public_partnership: __( 'Public partnership', 'woocommerce-payments' ),
		public_corporation: __( 'Public corporation', 'woocommerce-payments' ),
		incorporated_non_profit: __(
			'Incorporated non-profit',
			'woocommerce-payments'
		),
		unincorporated_non_profit: __(
			'Unincorporated non-profit',
			'woocommerce-payments'
		),
		governmental_unit: __( 'Governmental unit', 'woocommerce-payments' ),
		government_instrumentality: __(
			'Government instrumentality proprietorship',
			'woocommerce-payments'
		),
		tax_exempt_government_instrumentality: __(
			'Tax-exempt government instrumentality',
			'woocommerce-payments'
		),
	},
};

export default businessStructureStrings;
