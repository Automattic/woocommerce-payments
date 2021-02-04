export interface Charge {
	id: string;
	amount: number;
	currency: string;
}

export interface ChargesStateEntry {
	data?: Charge;
	error?: Error;
}
