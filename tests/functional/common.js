// Shared by the browser scripts: test-store ids, WP-CLI in the store's container and the
// results file run.sh reads. Every case is one "PASS|name" or "FAIL|name -- detail" line.
const { execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const STATE = process.env.MONERIS_STATE;
const CLI = process.env.MONERIS_CLI;
const IN_CONTAINER =
	'/var/www/html/wp-content/plugins/wc-moneris-payment-gateway/tests/functional';
const ids = JSON.parse(
	fs.readFileSync( path.join( STATE, 'ids.json' ), 'utf8' )
);

const record = ( ok, name, detail ) => {
	const line = `${ ok ? 'PASS' : 'FAIL' }|${ name }${
		ok || ! detail ? '' : ' -- ' + detail
	}`;
	fs.appendFileSync( path.join( STATE, 'results' ), line + '\n' );
	console.log( '  ' + line );
	return ok;
};

const wp = ( ...args ) =>
	execFileSync(
		'docker',
		[
			'exec',
			'-e',
			`MONERIS_RESULTS=${ IN_CONTAINER }/.tmp/state/results`,
			CLI,
			'wp',
			...args,
		],
		{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] }
	);
// PHP checks record their own cases in the results file; print them here too.
const php = ( file, ...args ) => {
	const out = wp( 'eval-file', `${ IN_CONTAINER }/${ file }`, ...args );
	out.split( '\n' )
		.filter( ( l ) => /^(PASS|FAIL)\|/.test( l ) )
		.forEach( ( l ) => console.log( '  ' + l ) );
	return out;
};
const settings = () =>
	JSON.parse(
		wp( 'option', 'get', 'woocommerce_moneris_settings', '--format=json' )
	);

// A crash anywhere is a FAIL, never a silent stop; run.sh also fails a missing last case.
const guard = ( script ) => {
	const crashed = ( kind ) => ( e ) => {
		const lines = String( ( e && e.stack ) || e ).split( '\n' );
		try {
			record(
				false,
				`${ script }: ${ kind }`,
				[
					lines[ 0 ],
					( lines.find( ( l ) => /^\s+at /.test( l ) ) || '' ).trim(),
				]
					.filter( Boolean )
					.join( ' ' )
			);
		} catch ( ignored ) {}
		process.exit( 1 );
	};
	process.on(
		'unhandledRejection',
		crashed( 'no unhandled promise rejection' )
	);
	process.on( 'uncaughtException', crashed( 'no uncaught exception' ) );
};

// The login page focuses the username box from a script after it loads, which can move
// typing between the boxes: wait for it, then fill and check both before submitting.
async function login( page, user, pass ) {
	await page.goto( ids.admin.replace( /wp-admin\/?$/, 'wp-login.php' ) );
	await page.waitForLoadState( 'networkidle' );
	await page.waitForTimeout( 500 );
	for ( let i = 0; i < 3; i++ ) {
		await page.fill( '#user_login', user );
		await page.fill( '#user_pass', pass );
		if (
			( await page.inputValue( '#user_login' ) ) === user &&
			( await page.inputValue( '#user_pass' ) ) === pass
		) {
			break;
		}
	}
	await Promise.all( [
		page.waitForURL( ( u ) => u.pathname.startsWith( '/wp-admin' ), {
			timeout: 60000,
		} ),
		page.click( '#wp-submit' ),
	] );
}

// WordPress 7 admin turns on cross-page view transitions (wp-admin/css/view-transitions.css);
// Chromium reports "Transition was skipped" when a test navigates before one can run.
// That exact message comes from the browser and WordPress, never from this plugin.
const SKIPPED = ( message ) => message === 'Transition was skipped';

module.exports = {
	SKIPPED,
	ids,
	record,
	wp,
	php,
	settings,
	guard,
	login,
	STATE,
};
