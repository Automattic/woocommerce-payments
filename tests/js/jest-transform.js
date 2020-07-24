module.exports = require( 'babel-jest' ).createTransformer( {
	presets: [
		[
			'@babel/preset-env',
			{
				modules: 'cjs',
				targets: {
					node: 11,
				},
			},
		],
	],
} );
