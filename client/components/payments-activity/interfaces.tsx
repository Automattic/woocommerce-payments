export interface DateRange {
	date_start: string; // Start date
	date_end: string; // End date
}

export interface PaymentsActivityData {
	paymentActivityData: {
		// Data for the payments activity
		total_payments_volume: number; // Total payments volume
		charges: number; // Charges
		fees: number; // Fees
		disputes: number; // Disputes
		refunds: number; // Refunds
	};
	isLoading: boolean; // Loading state of the data
}

export interface PaymentsDataTileProps {
	id: string; // id of the element can be used for CSS styling
	label: string; // The label appears as title of the tile
	currencyCode: string; // The currency to be displayed in the tile
	tooltip?: React.ReactElement; // For optionally passing the ClickTooltip component
	amount?: number; // The amount to be displayed in the tile
	isLoading?: boolean; // Loading state of the tile
	reportLink?: string; // Optional hover link to view report
}
