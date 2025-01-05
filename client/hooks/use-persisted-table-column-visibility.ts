/**
 * External dependencies
 */
import { useMemo } from '@wordpress/element';
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
	wc_payments_capital_hidden_columns: string[] | '';
}

export const usePersistedColumnVisibility = <
	ColumnType extends TableCardColumn
>(
	/**
	 * The key used to store the user's preference for hidden columns in the `wp_usermeta` table.
	 *
	 * This value will be prepended with `woocommerce_admin_` and used as the `meta_key` in the DB.
	 * */
	columnPrefsKey: keyof UserPreferences,
	/**
	 * The array of all columns to be passed to the `TableCard` component.
	 *
	 * Visibility of each column will adhere to stored user preferences using the column's `visible` prop.
	 *
	 * If the user's preference is not found, the default visibility value provided in the column's `visible` prop is used.
	 */
	allColumns: ColumnType[]
) => {
	const { updateUserPreferences, ...userPrefs } = useUserPreferences();

	// If returned value is undefined or empty string, use default visibility value.
	const userPrefHiddenColumns =
		( ( userPrefs as unknown ) as UserPreferences )[ columnPrefsKey ] ?? '';

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

	/**
	 * Memoized array of columns to be displayed.
	 *
	 * This array is created by mapping over the `allColumns` array and applying the user's
	 * preference for hidden columns to each column. If the user's preference is not found, the
	 * default visibility value is used.
	 */
	const columnsToDisplay = useMemo( () => {
		return allColumns.map( ( column ) => {
			// If user preference of hidden columns is not set, use default visibility value.
			return {
				...column,
				visible:
					userPrefHiddenColumns === ''
						? column.visible
						: // If the user preference is set, don't show hidden columns.
						  ! userPrefHiddenColumns.includes( column.key ),
			};
		} );
	}, [ allColumns, userPrefHiddenColumns ] );

	return {
		/** A function to be passed to the `TableCard` component's `onColumnsChange` prop. */
		onColumnsChange,
		/** An array of columns to be passed to the `TableCard` component's `columns` prop. */
		columnsToDisplay,
	};
};
