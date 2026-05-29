/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import OrdersTable from '../orders-table';

describe( 'OrdersTable', () => {
	it( 'renders the empty-state row when orders is undefined', () => {
		render( <OrdersTable /> );

		expect(
			screen.getByText(
				/No WSN orders yet — first WSN purchase will appear here/i
			)
		).toBeInTheDocument();
	} );

	it( 'renders the empty-state row when orders is an empty array', () => {
		render( <OrdersTable orders={ [] } /> );

		expect(
			screen.getByText(
				/No WSN orders yet — first WSN purchase will appear here/i
			)
		).toBeInTheDocument();
	} );

	it( 'renders order rows with formatted fields when populated', () => {
		const orders = [
			{
				id: 1,
				number: '3755',
				customer_name: 'Piero Rocca',
				date_relative: '20 hours ago',
				status: 'processing',
				status_label: 'Processing',
				items: [ 'Hoodie with Pocket' ],
				source: 'a',
				total_formatted: '$38.77',
				edit_url: 'https://example.test/wp-admin/post.php?post=1',
			},
		];

		render( <OrdersTable orders={ orders } /> );

		// Order link uses the `#<number>` format from the v2 mockup.
		expect( screen.getByText( '#3755' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Piero Rocca' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Hoodie with Pocket' ) ).toBeInTheDocument();
		expect( screen.getByText( '$38.77' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Processing' ) ).toBeInTheDocument();
	} );

	it( 'collapses long item lists into "+N more" suffix', () => {
		const orders = [
			{
				id: 1,
				number: '3747',
				customer_name: 'Marcus Johnson',
				date_relative: '3 days ago',
				status: 'processing',
				status_label: 'Processing',
				items: [
					'Hoodie with Logo - Large',
					'Paris Jacket',
					'Belt',
					'Sneakers',
				],
				source: 'a',
				total_formatted: '$215.97',
				edit_url: 'https://example.test/wp-admin/post.php?post=1',
			},
		];

		render( <OrdersTable orders={ orders } /> );

		// 4-item list shows first 2 + "+2 more" — keeps row single-line on narrow viewports.
		expect(
			screen.getByText(
				'Hoodie with Logo - Large, Paris Jacket, +2 more'
			)
		).toBeInTheDocument();
	} );

	it( 'renders em dash when customer_name is missing', () => {
		const orders = [
			{
				id: 1,
				number: '3700',
				customer_name: '',
				date_relative: '1 day ago',
				status: 'completed',
				status_label: 'Completed',
				items: [ 'Gift Card' ],
				source: null,
				total_formatted: '$25.00',
				edit_url: 'https://example.test/wp-admin/post.php?post=1',
			},
		];

		render( <OrdersTable orders={ orders } /> );

		// Customer column + Source column should both fall back to em dash.
		expect( screen.getAllByText( '—' ).length ).toBeGreaterThanOrEqual( 2 );
	} );
} );
