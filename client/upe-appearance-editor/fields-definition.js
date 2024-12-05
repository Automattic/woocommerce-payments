function componentToHex( val ) {
	const a = Number( val ).toString( 16 );
	return a.length === 1 ? '0' + a : a;
}

export function rgbToHex( rgb ) {
	if ( rgb.startsWith( '#' ) ) {
		return rgb;
	}
	return '#' + rgb.match( /\d+/g ).map( componentToHex ).join( '' );
}

const fieldDefaults = {
	type: 'text',
	transformInput: ( value ) => value,
	transformValue: ( value ) => value,
};

const borderStyleFieldDefaults = {
	...fieldDefaults,
	type: 'select',
	options: [
		{ label: 'None', value: 'none' },
		{ label: 'Solid', value: 'solid' },
		{ label: 'Dashed', value: 'dashed' },
		{ label: 'Dotted', value: 'dotted' },
		{ label: 'Double', value: 'double' },
		{ label: 'Groove', value: 'groove' },
		{ label: 'Ridge', value: 'ridge' },
		{ label: 'Inset', value: 'inset' },
		{ label: 'Outset', value: 'outset' },
	],
};

const colorFieldDefaults = {
	...fieldDefaults,
	type: 'color',
	transformInput: rgbToHex,
};

const pxFieldDefaults = {
	...fieldDefaults,
	type: 'number',
	step: 1,
	transformInput: ( value ) => value.replace( 'px', '' ),
	transformValue: ( value ) => `${ value }px`,
};

const fieldsDefinition = [
	{
		...colorFieldDefaults,
		label: 'Background Color',
		property: 'backgroundColor',
	},
	{
		...colorFieldDefaults,
		label: 'Text Color',
		property: 'color',
	},
	{
		...pxFieldDefaults,
		label: 'Font Size',
		property: 'fontSize',
	},
	{
		...pxFieldDefaults,
		label: 'Line Height',
		property: 'lineHeight',
		excludeFrom: [ '.Label', '.Text' ],
	},
	{
		...borderStyleFieldDefaults,
		label: 'Border style',
		property: 'borderBottomStyle',
		linkedProperties: [
			'borderLeftStyle',
			'borderRightStyle',
			'borderTopStyle',
		],
	},
	{
		...colorFieldDefaults,
		label: 'Border color',
		property: 'borderBottomColor',
		linkedProperties: [
			'borderLeftColor',
			'borderRightColor',
			'borderTopColor',
		],
	},
	{
		...pxFieldDefaults,
		label: 'Border radius',
		property: 'borderTopLeftRadius',
		linkedProperties: [
			'borderTopRightRadius',
			'borderBottomLeftRadius',
			'borderBottomRightRadius',
		],
	},
	{
		...pxFieldDefaults,
		label: 'Border bottom width',
		property: 'borderBottomWidth',
	},
	// {
	// 	...borderStyleFieldDefaults,
	// 	label: 'Border left style',
	// 	property: 'borderLeftStyle',
	// },
	// {
	// 	...colorFieldDefaults,
	// 	label: 'Border left color',
	// 	property: 'borderLeftColor',
	// },
	{
		...pxFieldDefaults,
		label: 'Border left width',
		property: 'borderLeftWidth',
	},
	// {
	// 	...borderStyleFieldDefaults,
	// 	label: 'Border right style',
	// 	property: 'borderRightStyle',
	// },
	// {
	// 	...colorFieldDefaults,
	// 	label: 'Border right color',
	// 	property: 'borderRightColor',
	// },
	{
		...pxFieldDefaults,
		label: 'Border right width',
		property: 'borderRightWidth',
	},
	// {
	// 	...borderStyleFieldDefaults,
	// 	label: 'Border top style',
	// 	property: 'borderTopStyle',
	// },
	// {
	// 	...colorFieldDefaults,
	// 	label: 'Border top color',
	// 	property: 'borderTopColor',
	// },
	{
		...pxFieldDefaults,
		label: 'Border top width',
		property: 'borderTopWidth',
	},
	{
		...fieldDefaults,
		label: 'Box Shadow',
		property: 'boxShadow',
	},
];

export default fieldsDefinition;
