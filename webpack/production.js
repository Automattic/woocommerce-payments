const BundleAnalyzerPlugin = require( 'webpack-bundle-analyzer' )
	.BundleAnalyzerPlugin;

module.exports = {
	devtool: 'hidden-source-map',
	plugins: [ new BundleAnalyzerPlugin( { defaultSizes: 'gzip' } ) ],
};
