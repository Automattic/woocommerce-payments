/* eslint-disable max-len */
/*
Adapted from @automattic/webpack-rtl-plugin, that was originally adapted and released by Romain Berger under the MIT License (MIT):

MIT License

Copyright (c) 2016 Romain Berger

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

const rtlcss = require( 'rtlcss' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const { ConcatSource } = require( 'webpack' ).sources;

const pluginName = 'WebpackRTLPlugin';
const cssRe = /\.css(?=$|\?)/;

class WebpackRTLPlugin {
	constructor( options ) {
		this.options = {
			options: {},
			plugins: [],
			filenameSuffix: null,
			...options,
		};
		this.cache = new WeakMap();
	}

	apply( compiler ) {
		const filenameSuffix = this.options.filenameSuffix || '.rtl$&';

		compiler.hooks.thisCompilation.tap( pluginName, ( compilation ) => {
			// WordPress swaps in the RTL file only for stylesheets it enqueues.
			// Lazy chunk CSS is loaded by the webpack runtime instead, so the
			// runtime has to pick the RTL file itself. WordPress sets
			// `<html dir="rtl">` whenever `is_rtl()` is true, which makes
			// `document.dir` work for every entry without extra globals.
			// The runtime looks up loaded tags by `data-href || href` against
			// the LTR URL, so `data-href` keeps that URL for de-duplication
			// and HMR.
			MiniCssExtractPlugin.getCompilationHooks(
				compilation
			).beforeTagInsert.tap(
				pluginName,
				( source, { tag, href } ) =>
					`${ source }\nif (document.dir === "rtl") { ${ tag }.setAttribute("data-href", ${ href }); ${ tag }.href = ${ href }.replace(${ cssRe }, ${ JSON.stringify(
						filenameSuffix
					) }); }`
			);

			compilation.hooks.processAssets.tapPromise(
				{
					name: pluginName,
					stage: compilation.PROCESS_ASSETS_STAGE_DERIVED,
				},
				async ( assets ) => {
					return Promise.all(
						Array.from( compilation.chunks )
							.flatMap( ( chunk ) =>
								// Collect all files form all chunks, and generate an array of {chunk, file} objects
								Array.from( chunk.files ).map( ( asset ) => ( {
									chunk,
									asset,
								} ) )
							)
							.filter( ( { asset } ) => cssRe.test( asset ) )
							.map( async ( { chunk, asset } ) => {
								if ( this.options.test ) {
									const re = new RegExp( this.options.test );
									if ( ! re.test( asset ) ) {
										return;
									}
								}

								// Compute the filename
								const filename = asset.replace(
									cssRe,
									filenameSuffix
								);
								const assetInstance = assets[ asset ];
								chunk.files.add( filename );

								if ( this.cache.has( assetInstance ) ) {
									const cachedRTL =
										this.cache.get( assetInstance );
									assets[ filename ] = cachedRTL;
								} else {
									const baseSource = assetInstance.source();
									const rtlSource = rtlcss.process(
										baseSource,
										this.options.options,
										this.options.plugins
									);
									// Save the asset
									assets[ filename ] = new ConcatSource(
										rtlSource
									);
									this.cache.set(
										assetInstance,
										assets[ filename ]
									);
								}
							} )
					);
				}
			);
		} );
	}
}

module.exports = WebpackRTLPlugin;
