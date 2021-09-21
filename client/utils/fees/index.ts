/**
 * Returns the given decimal fee as a percentage with
 * a maximum of 3 decimal places.
 *
 * @param {number} fee The fee represented as a decimal.
 * @return {number} The fee represented as a percentage.
 */
export const formatFee = ( fee: number ): number => {
	return Number( ( fee * 100 ).toFixed( 3 ) );
};
