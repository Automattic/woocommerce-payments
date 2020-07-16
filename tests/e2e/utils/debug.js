export function dumpFrameTree( frame, indent ) {
	// eslint-disable-next-line no-console
	console.log( indent + frame.url() );
	for ( const child of frame.childFrames() ) {
		dumpFrameTree( child, indent + '  ' );
	}
}
