/** @format */

export type DateOperator = 'on' | 'before' | 'after' | 'between';

export type DateFilterValue =
	| { operator: 'on'; value: string }
	| { operator: 'before'; value: string }
	| { operator: 'after'; value: string }
	| { operator: 'between'; value: [ string, string ] };

export interface DateFilterPresetElement {
	value: string;
	label: string;
}
