/** @format */

export interface ReportsPeriodRange {
	start: string;
	end: string;
}

export function getLastFullCalendarMonthUTC(
	now: Date = new Date()
): ReportsPeriodRange {
	const start = new Date(
		Date.UTC( now.getUTCFullYear(), now.getUTCMonth() - 1, 1, 0, 0, 0 )
	);
	const end = new Date(
		Date.UTC( now.getUTCFullYear(), now.getUTCMonth(), 0, 23, 59, 59, 999 )
	);

	return {
		start: start.toISOString(),
		end: end.toISOString(),
	};
}
