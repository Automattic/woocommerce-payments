export interface Deposit {
	automatic: boolean;
	date: number;
	status: string; // TODO: list statuses
	amount: number;
	currency: string;
	bankAccount: string;
	fee: number;
	fee_percentage: number;
}
