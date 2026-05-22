/** @format */

/**
 * Internal dependencies
 */
import { formatExplicitCurrency } from 'multi-currency/interface/functions';

/**
 * Formats a Balance summary amount in the "code-first with leading sign" style
 * used by the Balance report Figma design — e.g. `+USD 1,234.00` or `-USD 80.00`.
 *
 * The default `formatExplicitCurrency` output puts the symbol before the amount
 * and the ISO code after it (`$1,234.00 USD`). Balance rows show every amount
 * with an explicit sign and the ISO code in front, with no currency symbol, so
 * that running totals are easy to scan and reconciliation against bank
 * statements doesn't require eyeballing past a `$`.
 */
export const formatBalanceAmount = (
	amount: number,
	currencyCode: string
): string => {
	const upperCode = currencyCode.toUpperCase();
	const isNegative = amount < 0;
	const isZero = amount === 0;
	const absoluteAmount = isNegative ? -amount : amount;

	// `skipSymbol = true` removes the leading `$` (etc.) while keeping the
	// currency utility's locale-aware number formatting. Depending on explicit
	// pricing settings, the helper may also append a trailing ISO code.
	const formatted = formatExplicitCurrency( absoluteAmount, upperCode, true );

	// `formatExplicitCurrency` returns the value in one of two shapes depending
	// on whether explicit pricing is enabled in settings:
	//   • "1,234.00 USD"     — explicit pricing on (the common case)
	//   • "1,234.00"         — explicit pricing off
	// Move the ISO code to the front to match the Figma "code-first" pattern,
	// and drop any stray spaces left behind by the symbol removal.
	const withoutCode = formatted
		.replace( new RegExp( `\\s*${ upperCode }\\s*$` ), '' )
		.trim();

	// Zero balances render without a sign so reconciliation reports don't pin a
	// misleading positive or negative direction onto an empty line.
	if ( isZero ) {
		return `${ upperCode } ${ withoutCode }`;
	}

	const sign = isNegative ? '-' : '+';

	return `${ sign }${ upperCode } ${ withoutCode }`;
};
