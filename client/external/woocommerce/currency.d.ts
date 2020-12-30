
declare module '@woocommerce/currency' {
	class Currency {
		constructor();

		formatCurrency: ( arg0: number ) => string;
	}

	export = Currency;
}
