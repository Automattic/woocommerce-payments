/**
 * External dependencies
 */
import { useMemo } from 'react';
import { useUserPreferences } from '@woocommerce/data';
import type { TableCardColumn } from '@woocommerce/components';

/**
 * Type for user preferences returned from useUserPreferences hook.
 *
 * These preference keys are defined and managed in:
 *
 * @see WC_Payments::add_user_data_fields() in includes/class-wc-payments.php
 *
 * Note: This interface must stay in sync with the PHP implementation
 */
interface UserPreferences {
	wc_payments_transactions_hidden_columns: string[] | '';
	wc_payments_transactions_blocked_hidden_columns: string[] | '';
	wc_payments_transactions_risk_review_hidden_columns: string[] | '';
	wc_payments_transactions_uncaptured_hidden_columns: string[] | '';
	wc_payments_payouts_hidden_columns: string[] | '';
	wc_payments_disputes_hidden_columns: string[] | '';
	wc_payments_documents_hidden_columns: string[] | '';
}

/**
 * Hook to manage column visibility for a TableCard component.
 *
 * This hook is used to manage the visibility of columns in a TableCard component.
 * It uses the `@woocommerce/data` `useUserPreferences` hook to get the user's preferences and store them in the `wp_usermeta` table.
 */
export const usePersistedColumnVisibility = <
	ColumnType extends TableCardColumn
>(
	/**
	 * The key used to store the user's preference for hidden columns in the `wp_usermeta` table.
	 *
	 * This value will be prepended with `woocommerce_admin_` and used as the `meta_key` in the DB.
	 */
	columnPrefsKey: keyof UserPreferences,
	/**
	 * The array of all columns to be passed to the `TableCard` component.
	 *
	 * Visibility of each column will adhere to stored user preferences using the column's `visible` prop.
	 *
	 * If the user's preference is not found, the default visibility value provided in the column's `visible` prop is used.
	 */
	allColumns: ColumnType[]
): {
	/**
	 * A function to be passed to the `TableCard` component's `onColumnsChange` prop.
	 *
	 * This function is used to update the user's preference for hidden columns on each column visibility change event.
	 */
	onColumnsChange: ( shownColumns: string[] ) => void;
	/**
	 * An array of columns to be passed to the `TableCard` component's `columns` prop, with visibility of columns adhering to stored user preferences.
	 *
	 * If the user's preference is not found, the default visibility value of each column is used.
	 */
	columnsToDisplay: ColumnType[];
} => {
	const { updateUserPreferences, ...userPrefs } = useUserPreferences();

	// If returned value is undefined or empty string, use default visibility value.
	const userPrefHiddenColumns =
		( ( userPrefs as unknown ) as UserPreferences )[ columnPrefsKey ] ?? '';

	// When the user changes the column visibility, update the user's preference for hidden columns.
	const onColumnsChange = ( shownColumns: string[] ) => {
		const columns = allColumns.map( ( column ) => column.key );
		const hiddenColumns = columns.filter(
			( column ) => ! shownColumns.includes( column )
		);
		if ( columnPrefsKey ) {
			const userDataFields = {
				[ columnPrefsKey ]: hiddenColumns,
			};
			updateUserPreferences( userDataFields );
		}
	};

	// When the user's preference for hidden columns is updated, update the columns to display.
	const columnsToDisplay = useMemo( () => {
		// If the user preference is not set (is empty string), return all columns with default visibility.
		if ( ! Array.isArray( userPrefHiddenColumns ) ) {
			return allColumns;
		}

		// If the user preference is set, hide the column.
		return allColumns.map( ( column ) => {
			return {
				...column,
				visible: ! userPrefHiddenColumns.includes( column.key ),
			};
		} );
	}, [ allColumns, userPrefHiddenColumns ] );

	return {
		onColumnsChange,
		columnsToDisplay,
	};
};
