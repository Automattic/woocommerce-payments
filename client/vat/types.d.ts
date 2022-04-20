export interface VatValidationResult {
	address: string | null;
	country_code: string;
	name: string | null;
	valid: boolean;
	vat_number: string;
}

export type VatFormOnCompleted = (
	vatNumber: string | null,
	companyName: string,
	companyAddress: string
) => void;

export interface VatError {
	code: string;
	message: string;
}
