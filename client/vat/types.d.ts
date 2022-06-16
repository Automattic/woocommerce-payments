export interface VatValidationResult {
	address: string | null;
	country_code: string;
	name: string | null;
	valid: boolean;
	vat_number: string;
}

export interface VatSaveDetails {
	vat_number?: string;
	name: string;
	address: string;
}

export interface VatSaveDetailsResult {
	vat_number: string | null;
	name: string;
	address: string;
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
