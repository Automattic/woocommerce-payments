/**
 * External dependencies
 */
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import '../wc-product-bundles';

describe( 'ECE product bundles compatibility', () => {
	it( 'filters out cart items that are bundled by something else', () => {
		const cartData = applyFilters(
			'wcpay.express-checkout.map-line-items',
			{
				items: [
					{
						key: 'd179a6924eafc82d7864f1e0caedbe95',
						id: 261,
						type: 'bundle',
						quantity: 1,
						item_data: [
							{
								key: 'Includes',
								value: 'T-Shirt &times; 1',
							},
							{
								key: 'Includes',
								value: 'T-Shirt with Logo &times; 2',
							},
							{
								key: 'Includes',
								value: 'V-Neck T-Shirt - Medium &times; 1',
							},
						],
						extensions: {
							bundles: {
								bundled_items: [
									'abda15f782e68dc63bd615d6a05fa3d2',
									'4d16fa6ebc10a1d66013b0f85640eb2b',
									'ff279cc5574ef1cf45aa76bde0d66baa',
								],
								bundle_data: {
									configuration: {
										'1': {
											product_id: 13,
											quantity: 1,
											discount: 20,
											optional_selected: 'yes',
										},
										'2': {
											product_id: 30,
											quantity: 2,
											discount: '',
										},
										'3': {
											product_id: 10,
											quantity: 1,
											discount: '',
											attributes: {
												attribute_size: 'Medium',
											},
											variation_id: '25',
										},
										'4': {
											product_id: 10,
											quantity: 0,
											discount: '',
											optional_selected: 'no',
											attributes: [],
										},
									},
									is_editable: false,
									is_price_hidden: false,
									is_subtotal_hidden: false,
									is_hidden: false,
									is_meta_hidden_in_cart: true,
									is_meta_hidden_in_summary: false,
								},
							},
						},
					},
					{
						key: 'abda15f782e68dc63bd615d6a05fa3d2',
						id: 13,
						type: 'simple',
						quantity: 1,
						extensions: {
							bundles: {
								bundled_by: 'd179a6924eafc82d7864f1e0caedbe95',
								bundled_item_data: {
									bundle_id: 261,
									bundled_item_id: 1,
									is_removable: true,
									is_indented: true,
									is_subtotal_aggregated: true,
									is_parent_visible: true,
									is_last: false,
									is_price_hidden: false,
									is_subtotal_hidden: false,
									is_thumbnail_hidden: false,
									is_hidden_in_cart: false,
									is_hidden_in_summary: true,
								},
							},
						},
					},
					{
						key: '4d16fa6ebc10a1d66013b0f85640eb2b',
						id: 30,
						type: 'simple',
						quantity: 2,
						extensions: {
							bundles: {
								bundled_by: 'd179a6924eafc82d7864f1e0caedbe95',
								bundled_item_data: {
									bundle_id: 261,
									bundled_item_id: 2,
									is_removable: false,
									is_indented: true,
									is_subtotal_aggregated: true,
									is_parent_visible: true,
									is_last: false,
									is_price_hidden: true,
									is_subtotal_hidden: true,
									is_thumbnail_hidden: false,
									is_hidden_in_cart: false,
									is_hidden_in_summary: true,
								},
							},
						},
					},
					{
						key: 'ff279cc5574ef1cf45aa76bde0d66baa',
						id: 25,
						type: 'variation',
						quantity: 1,
						extensions: {
							bundles: {
								bundled_by: 'd179a6924eafc82d7864f1e0caedbe95',
								bundled_item_data: {
									bundle_id: 261,
									bundled_item_id: 3,
									is_removable: false,
									is_indented: true,
									is_subtotal_aggregated: true,
									is_parent_visible: true,
									is_last: true,
									is_price_hidden: true,
									is_subtotal_hidden: true,
									is_thumbnail_hidden: false,
									is_hidden_in_cart: false,
									is_hidden_in_summary: true,
								},
							},
						},
					},
					{
						key: 'c51ce410c124a10e0db5e4b97fc2af39',
						id: 13,
						type: 'simple',
						quantity: 1,
						extensions: {
							bundles: [],
						},
					},
				],
				items_count: 2,
			}
		);

		expect( cartData ).toStrictEqual( {
			items: [
				{
					extensions: {
						bundles: {
							bundle_data: {
								configuration: {
									'1': {
										discount: 20,
										optional_selected: 'yes',
										product_id: 13,
										quantity: 1,
									},
									'2': {
										discount: '',
										product_id: 30,
										quantity: 2,
									},
									'3': {
										attributes: {
											attribute_size: 'Medium',
										},
										discount: '',
										product_id: 10,
										quantity: 1,
										variation_id: '25',
									},
									'4': {
										attributes: [],
										discount: '',
										optional_selected: 'no',
										product_id: 10,
										quantity: 0,
									},
								},
								is_editable: false,
								is_hidden: false,
								is_meta_hidden_in_cart: true,
								is_meta_hidden_in_summary: false,
								is_price_hidden: false,
								is_subtotal_hidden: false,
							},
							bundled_items: [
								'abda15f782e68dc63bd615d6a05fa3d2',
								'4d16fa6ebc10a1d66013b0f85640eb2b',
								'ff279cc5574ef1cf45aa76bde0d66baa',
							],
						},
					},
					id: 261,
					item_data: [
						{
							key: 'Includes',
							value: 'T-Shirt &times; 1',
						},
						{
							key: 'Includes',
							value: 'T-Shirt with Logo &times; 2',
						},
						{
							key: 'Includes',
							value: 'V-Neck T-Shirt - Medium &times; 1',
						},
					],
					key: 'd179a6924eafc82d7864f1e0caedbe95',
					quantity: 1,
					type: 'bundle',
				},
				{
					extensions: {
						bundles: [],
					},
					id: 13,
					key: 'c51ce410c124a10e0db5e4b97fc2af39',
					quantity: 1,
					type: 'simple',
				},
			],
			items_count: 2,
		} );
	} );
} );
