/** @format */

import type { View, ViewTable } from '@wordpress/dataviews/wp';

export const feesViewUserMetaKey = 'wc_payments_reports_fees_view';

export type FeesFieldId =
	| 'date'
	| 'payment_method'
	| 'type'
	| 'order_id'
	| 'transaction_id'
	| 'transaction_currency'
	| 'amount'
	| 'fees'
	| 'deposit_date'
	| 'deposit_id';

const defaultVisibleFields: FeesFieldId[] = [
	'transaction_id',
	'payment_method',
	'type',
	'order_id',
	'transaction_currency',
	'amount',
	'fees',
];

export const defaultPerPage = 25;
export const feesPrimaryField: FeesFieldId = 'date';

export const getFeesTableFields = (
	fields: readonly string[] = defaultVisibleFields
): FeesFieldId[] =>
	fields.filter( ( field ) => field !== feesPrimaryField ) as FeesFieldId[];

/**
 * Subset of the View object that we persist to user_meta. Sort/page/search/filters
 * live in the URL; only layout-ish state goes to user_meta so a user's preferred
 * column set and density survive across sessions without polluting bookmarks.
 * Density is stored inside `layout`, not at the top level.
 */
export interface PersistedFeesView {
	fields: FeesFieldId[];
	layout?: ViewTable[ 'layout' ];
	perPage?: number;
}

export const getFeesTableLayout = (
	layout: ViewTable[ 'layout' ] = {}
): ViewTable[ 'layout' ] => {
	const styles = layout?.styles ?? {};

	return {
		...layout,
		styles: {
			...styles,
			amount: {
				...styles.amount,
				align: 'start',
			},
			fees: {
				...styles.fees,
				align: 'start',
			},
		},
	};
};

export const getDefaultFeesView = (): View => ( {
	type: 'table',
	search: '',
	filters: [],
	page: 1,
	perPage: defaultPerPage,
	sort: { field: 'date', direction: 'desc' },
	titleField: feesPrimaryField,
	fields: getFeesTableFields(),
	layout: getFeesTableLayout(),
} );
