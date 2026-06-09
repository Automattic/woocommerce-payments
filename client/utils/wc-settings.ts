export const getSetting = ( key: string ): string => {
	const wcSettings = (
		window as unknown as {
			wc?: { wcSettings?: { getSetting?: ( k: string ) => unknown } };
		}
	 ).wc?.wcSettings;
	const value = wcSettings?.getSetting?.( key );
	return value == null ? '' : String( value );
};
