/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { SelectControl, TextareaControl } from '@wordpress/components';

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
	const isAdditionalEvidenceTypesEnabled =
		wcpaySettings?.featureFlags?.isDisputeAdditionalEvidenceTypesEnabled ||
		false;

	const productTypeOptions = [
		{
			label: __( 'Physical products', 'woocommerce-payments' ),
			value: 'physical_product',
		},
		{
			label: __( 'Digital products', 'woocommerce-payments' ),
			value: 'digital_product_or_service',
		},
		{
			label: __( 'Offline service', 'woocommerce-payments' ),
			value: 'offline_service',
		},
		...( isAdditionalEvidenceTypesEnabled
			? [
					{
						label: __(
							'Booking/Reservation',
							'woocommerce-payments'
						),
						value: 'booking_reservation',
					},
			  ]
			: [] ),
		{
			label: __( 'Multiple product types', 'woocommerce-payments' ),
			value: 'multiple',
		},
	];

	return (
		<section className="wcpay-dispute-evidence-product-details">
			<h3 className="wcpay-dispute-evidence-product-details__heading">
				{ isAdditionalEvidenceTypesEnabled
					? __( 'Product or service details', 'woocommerce-payments' )
					: __( 'Product details', 'woocommerce-payments' ) }
			</h3>
			<div className="wcpay-dispute-evidence-product-details__subheading">
				{ isAdditionalEvidenceTypesEnabled
					? __(
							'Please ensure the product or service type and description have been entered accurately.',
							'woocommerce-payments'
					  )
					: __(
							'Please ensure the product type and description have been entered accurately.',
							'woocommerce-payments'
					  ) }
			</div>
			<div className="wcpay-dispute-evidence-product-details__field-group">
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={
						isAdditionalEvidenceTypesEnabled
							? __(
									'PRODUCT OR SERVICE TYPE',
									'woocommerce-payments'
							  )
							: __( 'PRODUCT TYPE', 'woocommerce-payments' )
					}
					value={ productType }
					onChange={ onProductTypeChange }
					data-testid={ 'dispute-challenge-product-type-selector' }
					options={ productTypeOptions }
					disabled={ readOnly }
				/>
			</div>
			<div className="wcpay-dispute-evidence-product-details__field-group">
				<TextareaControl
					__nextHasNoMarginBottom
					label={
						isAdditionalEvidenceTypesEnabled
							? __(
									'PRODUCT OR SERVICE DESCRIPTION',
									'woocommerce-payments'
							  )
							: __(
									'PRODUCT DESCRIPTION',
									'woocommerce-payments'
							  )
					}
					value={ productDescription }
					onChange={ onProductDescriptionChange }
					disabled={ readOnly }
				/>
			</div>
		</section>
	);
};

export default ProductDetails;
