/**
 * Converts a snake_case string to camelCase.
 *
 * @example snakeToCamel('apple_pay') → 'applePay'
 */
export function snakeToCamel( snake: string ): string {
	// eslint-disable-next-line @typescript-eslint/naming-convention
	return snake.replace( /_([a-z])/g, ( _m, letter ) => letter.toUpperCase() );
}
