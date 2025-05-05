export type VatFormOnCompleted = (
	vatNumber: string | null,
	companyName: string,
	companyAddress: string
) => void;

export interface VatError {
	code: string;
	message: string;
}
