/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	SelectControl,
	TextareaControl,
} from 'wcpay/components/wp-components-wrapped';

interface ProductDetailsProps {
	productType: string;
	onProductTypeChange: ( value: string ) => void;
	productDescription: string;
	onProductDescriptionChange: ( value: string ) => void;
	readOnly?: boolean;
}

const ProductDetails: React.FC< ProductDetailsProps > = ( {
	productType,
	onProductTypeChange,
	productDescription,
	onProductDescriptionChange,
	readOnly = false,
} ) => {
	return (
		<section className="wcpay-dispute-evidence-product-details">
			<h3 className="wcpay-dispute-evidence-product-details__heading">
				{ __( 'Product details', 'woocommerce-payments' ) }
			</h3>
			<div className="wcpay-dispute-evidence-product-details__field-group">
				<SelectControl
					label={ __( 'PRODUCT TYPE', 'woocommerce-payments' ) }
					help={ __(
						'First select the kind of product you fulfilled.',
						'woocommerce-payments'
					) }
					value={ productType }
					onChange={ onProductTypeChange }
					options={ [
						{
							label: __(
								'Physical products',
								'woocommerce-payments'
							),
							value: 'physical_product',
						},
						{
							label: __(
								'Digital products',
								'woocommerce-payments'
							),
							value: 'digital_product_or_service',
						},
						{
							label: __(
								'Offline service',
								'woocommerce-payments'
							),
							value: 'offline_service',
						},
						{
							label: __(
								'Multiple product types',
								'woocommerce-payments'
							),
							value: 'multiple',
						},
					] }
					disabled={ readOnly }
				/>
			</div>
			<div className="wcpay-dispute-evidence-product-details__field-group">
				<TextareaControl
					label={ __(
						'PRODUCT DESCRIPTION',
						'woocommerce-payments'
					) }
					help={ __(
						'Please make sure this is an accurate description of the product.',
						'woocommerce-payments'
					) }
					value={ productDescription }
					onChange={ ( value ) =>
						onProductDescriptionChange( value )
					}
					disabled={ readOnly }
				/>
			</div>
		</section>
	);
};

export default ProductDetails;
