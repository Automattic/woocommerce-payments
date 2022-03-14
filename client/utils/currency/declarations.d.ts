declare module '@woocommerce/currency' {
	interface CurrencyConfig {
		code?: string;
		symbols?: string;
		symbolPosition?: string;
		thousandSeparator?: string;
		decimalSeparator?: string;
		precision?: number;
	}

	interface CurrencySetting {
		[ countryCode: string ]: CurrencyConfig;
	}

	interface CurrencyInterface {
		formatAmount: ( n: number ) => string;
		formatCurrency: ( n: number ) => string;
	}

	export default function CurrencyFactory(
		currencyConfig: CurrencyConfig
	): CurrencyInterface;

	export function getCurrencyData(): CurrencySetting;
}
