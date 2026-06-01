#!/bin/bash
set -euo pipefail

# Fast harness sanity checks before paying the production build cost.
test -f client/index.js
test -f webpack.config.js
node -e "JSON.parse(require('fs').readFileSync('package.json', 'utf8')); require('./webpack/shared.js'); require('./webpack/production.js');" >/dev/null

BUILD_LOG="$(mktemp -t wcpay-autoresearch-build.XXXXXX.log)"
START_NS="$(node -e "process.stdout.write(process.hrtime.bigint().toString())")"
if ! NODE_ENV=production npm run build:client >"${BUILD_LOG}" 2>&1; then
	cat "${BUILD_LOG}" | tail -120
	rm -f "${BUILD_LOG}"
	exit 1
fi
END_NS="$(node -e "process.stdout.write(process.hrtime.bigint().toString())")"
BUILD_SECONDS="$(node -e "console.log(((BigInt(process.argv[2])-BigInt(process.argv[1]))/1000000n).toString())" "${START_NS}" "${END_NS}")"
BUILD_SECONDS="$(node -e "console.log((Number(process.argv[1]) / 1000).toFixed(3))" "${BUILD_SECONDS}")"

node <<'NODE'
const fs = require( 'fs' );
const path = require( 'path' );
const zlib = require( 'zlib' );

const dist = path.resolve( 'dist' );
const gzipKb = ( file ) =>
	zlib.gzipSync( fs.readFileSync( file ), { level: 9 } ).length / 1024;
const rawKb = ( file ) => fs.statSync( file ).size / 1024;

const initialAssets = [ 'index.js', 'index.css' ]
	.map( ( file ) => path.join( dist, file ) )
	.filter( fs.existsSync );

let allAssets = [];
const walk = ( dir ) => {
	for ( const entry of fs.readdirSync( dir, { withFileTypes: true } ) ) {
		const file = path.join( dir, entry.name );
		if ( entry.isDirectory() ) {
			walk( file );
			continue;
		}

		if ( ! /\.(js|css)$/.test( entry.name ) ) {
			continue;
		}

		// RTL CSS duplicates are generated from the same source CSS and are not part
		// of the default left-to-right initial payload. Track non-RTL JS/CSS only.
		if ( /-rtl\.css$/.test( entry.name ) ) {
			continue;
		}

		allAssets.push( file );
	}
};
walk( dist );

const initialGzipKb = initialAssets.reduce( ( total, file ) => total + gzipKb( file ), 0 );
const totalDistGzipKb = allAssets.reduce( ( total, file ) => total + gzipKb( file ), 0 );

console.log(
	'Initial assets:',
	initialAssets.map( ( file ) => path.relative( dist, file ) ).join( ', ' )
);
console.log(
	'Top gzip assets:',
	allAssets
		.map( ( file ) => [ path.relative( dist, file ), gzipKb( file ) ] )
		.sort( ( a, b ) => b[ 1 ] - a[ 1 ] )
		.slice( 0, 10 )
		.map( ( [ file, kb ] ) => `${ file }=${ kb.toFixed( 1 ) }kb` )
		.join( ', ' )
);
console.log( `METRIC admin_initial_gzip_kb=${ initialGzipKb.toFixed( 3 ) }` );
console.log( `METRIC total_dist_gzip_kb=${ totalDistGzipKb.toFixed( 3 ) }` );
console.log( `METRIC index_raw_kb=${ rawKb( path.join( dist, 'index.js' ) ).toFixed( 3 ) }` );
NODE
printf 'METRIC build_seconds=%s\n' "${BUILD_SECONDS}"
rm -f "${BUILD_LOG}"
