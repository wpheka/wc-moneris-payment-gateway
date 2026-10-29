// The WPHEKA > Moneris page in a real browser: every control, the save bar, Discard, the
// links WooCommerce and WordPress show, and who may open the page.
const { chromium } = require( 'playwright' );
const path = require( 'path' );
const fs = require( 'fs' );
const {
	SKIPPED,
	ids,
	record,
	settings,
	guard,
	login,
	STATE,
	wp,
} = require( './common' );

guard( 'admin.js' );
const PAGE = ids.admin + 'admin.php?page=wpheka-moneris';
const row = ( page, label ) =>
	page.locator( '.w-row', {
		has: page.locator( '.w-label', { hasText: label } ),
	} );
// Headless Chromium freezes CSS transitions after a POST and redirect (tasks/lessons.md), so
// Playwright never sees a switch or chip as stable: click the real checkbox, forced.
const flip = ( page, label ) =>
	row( page, label )
		.locator( 'input[type=checkbox]' )
		.click( { force: true } );
const chip = ( page, name ) =>
	page
		.locator( '.w-chip', { hasText: name } )
		.locator( 'input' )
		.click( { force: true } );

// The save bar slides in with a transition that headless Chromium can leave frozen
// off-screen after a POST and redirect: check the kit asked to show it, then press its
// real button through the DOM (the same submit a click makes).
async function save( page ) {
	await page
		.locator( '.w-savebar.is-shown' )
		.waitFor( { state: 'attached', timeout: 10000 } );
	await Promise.all( [
		page.waitForURL( /w-saved=1/, { timeout: 60000 } ),
		page
			.locator( '.w-savebar button[type="submit"]' )
			.evaluate( ( b ) => b.click() ),
	] );
	return (
		( await page.locator( '.w-toast.is-shown' ).textContent() ) || ''
	).includes( 'Settings saved' );
}

( async () => {
	const browser = await chromium.launch( {
		channel: process.env.PW_CHANNEL || undefined,
	} );
	const ctx = await browser.newContext( {
		viewport: { width: 1440, height: 1000 },
	} );
	const page = await ctx.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => {
		if ( ! SKIPPED( e.message ) ) {
			errors.push( e.message );
		}
	} );
	page.on( 'console', ( m ) => {
		if (
			m.type() === 'error' &&
			! /favicon|Failed to load resource/.test( m.text() )
		) {
			errors.push( m.text() );
		}
	} );

	await login( page, 'admin', 'password' );
	wp(
		'option',
		'update',
		'woocommerce_moneris_settings',
		JSON.stringify( {
			enabled: 'yes',
			title: 'Moneris',
			description: 'Pay with your credit card via moneris.',
			store_id: 'store5',
			api_token: 'yesguy',
			preferred_cards: [ 'visa', 'mastercard' ],
			sandbox: 'yes',
			logging: 'yes',
		} ),
		'--format=json'
	);
	const start = settings();

	// Every tab opens without a script error.
	for ( const tab of [ 'settings', 'transactions', 'help' ] ) {
		await page.goto( `${ PAGE }&tab=${ tab }` );
		record(
			(
				await page.locator( '.wpheka-ui h1' ).first().textContent()
			).includes( 'Moneris Payment Gateway' ),
			`admin page: ${ tab } tab opens`
		);
	}
	// WordPress's notices stay off the page; its own notices print in place through the kit.
	await page.goto( ids.admin + 'plugins.php' );
	record(
		( await page.locator( '.zz-mon-foreign' ).count() ) === 3,
		"other plugins' notices still show on the Plugins screen",
		String( await page.locator( '.zz-mon-foreign' ).count() )
	);
	record(
		( await page.locator( '.wpheka-plugin-donation-notice' ).count() ) ===
			1,
		"this plugin's donation notice still shows on the Plugins screen (WordPress's way)"
	);
	for ( const tab of [ 'settings', 'transactions', 'help' ] ) {
		await page.goto( `${ PAGE }&tab=${ tab }` );
		record(
			( await page.locator( '.zz-mon-foreign' ).count() ) === 0,
			`admin page (${ tab }): no hooked notices from anyone else`,
			String( await page.locator( '.zz-mon-foreign' ).count() )
		);
		record(
			! ( await page.locator( '.zz-mon-stray' ).isVisible() ),
			`admin page (${ tab }): a notice printed outside the hooks is hidden`
		);
		record(
			( await page
				.locator( '.wpheka-plugin-donation-notice' )
				.count() ) === 0,
			`admin page (${ tab }): no WordPress-style notice from this plugin either`
		);
		record(
			await page
				.locator( '.w-notices #wpheka-moneris-donation' )
				.isVisible(),
			`admin page (${ tab }): the donation notice shows through the kit`
		);
	}
	// Nothing moves once the page has loaded: the page's top is where it was at first paint.
	// The first position is read by a listener added before any page script, so it runs
	// ahead of WordPress's own script that moves notices once the HTML has loaded.
	await page.addInitScript( () =>
		document.addEventListener(
			'DOMContentLoaded',
			() => {
				const hero = document.querySelector( '.w-hero' );
				window.zzFirstTop = hero
					? hero.getBoundingClientRect().top
					: null;
			},
			{ capture: true }
		)
	);
	for ( const tab of [ 'settings', 'transactions', 'help' ] ) {
		await page.goto( `${ PAGE }&tab=${ tab }`, { waitUntil: 'load' } );
		const first = await page.evaluate( () => window.zzFirstTop );
		await page.waitForTimeout( 800 );
		const settled = await page
			.locator( '.w-hero' )
			.evaluate( ( el ) => el.getBoundingClientRect().top );
		record(
			first === settled,
			`admin page (${ tab }): header and content do not shift after the page loads`,
			`${ first }px then ${ settled }px`
		);
	}
	await page.goto( ids.admin + 'admin.php?page=wc-settings&tab=general' );
	record(
		( await page.locator( '.zz-mon-foreign' ).count() ) === 3,
		"other plugins' notices still show on WooCommerce's settings",
		String( await page.locator( '.zz-mon-foreign' ).count() )
	);
	// Dismissing the donation notice on the page works, and sticks.
	await page.goto( `${ PAGE }&tab=help` );
	const dismissed = page.waitForResponse( ( r ) =>
		r.url().includes( 'wpheka_gateway_moneris_donation_dismiss' )
	);
	await page
		.locator( '#wpheka-moneris-donation .w-close' )
		.evaluate( ( a ) => a.click() );
	await dismissed;
	record(
		( await page.locator( '#wpheka-moneris-donation' ).count() ) === 0,
		'donation notice: removed at once when dismissed'
	);
	await page.goto( `${ PAGE }&tab=help` );
	record(
		( await page.locator( '#wpheka-moneris-donation' ).count() ) === 0,
		'donation notice: stays dismissed after a reload'
	);
	await page.goto( ids.admin + 'plugins.php' );
	record(
		( await page.locator( '.wpheka-plugin-donation-notice' ).count() ) ===
			0,
		'donation notice: dismissed on the page is dismissed everywhere'
	);

	await page.goto( `${ PAGE }&tab=settings` );
	record(
		await page.locator( '.w-badge', { hasText: 'Sandbox' } ).isVisible(),
		'admin page: Sandbox badge while sandbox is on'
	);
	record(
		( await row( page, 'Store ID' ).locator( 'input' ).inputValue() ) ===
			'store5',
		'admin page: shows the saved Store ID'
	);

	// Sandbox off, saved, shown off; then back on.
	await flip( page, 'Sandbox mode' );
	record( await save( page ), 'admin page: saving shows "Settings saved"' );
	record(
		settings().sandbox === 'no',
		'admin page: sandbox switched off is saved as no',
		settings().sandbox
	);
	record(
		! ( await row( page, 'Sandbox mode' )
			.locator( 'input[type=checkbox]' )
			.isChecked() ),
		'admin page: sandbox shown off after the save'
	);
	record(
		! ( await page.locator( '.w-badge', { hasText: 'Sandbox' } ).count() ),
		'admin page: no Sandbox badge in live mode'
	);
	await flip( page, 'Sandbox mode' );
	await save( page );
	record(
		settings().sandbox === 'yes',
		'admin page: sandbox switched back on is saved as yes',
		settings().sandbox
	);

	// Enable and logging both ways.
	for ( const [ label, key ] of [
		[ 'Enable Moneris', 'enabled' ],
		[ 'Log gateway events', 'logging' ],
	] ) {
		await flip( page, label );
		await save( page );
		const off = settings()[ key ];
		await flip( page, label );
		await save( page );
		record(
			off === 'no' && settings()[ key ] === 'yes',
			`admin page: "${ label }" off then on saves no then yes`,
			`${ off } / ${ settings()[ key ] }`
		);
	}

	// Card logos: clear both, then pick them again.
	for ( const name of [ 'Visa', 'MasterCard' ] ) {
		await chip( page, name );
	}
	await save( page );
	record(
		JSON.stringify( settings().preferred_cards ) === '[]',
		'admin page: clearing every card logo saves an empty list',
		JSON.stringify( settings().preferred_cards )
	);
	for ( const name of [ 'Visa', 'MasterCard' ] ) {
		await chip( page, name );
	}
	await save( page );
	record(
		JSON.stringify( settings().preferred_cards ) ===
			'["visa","mastercard"]',
		'admin page: picking logos saves them',
		JSON.stringify( settings().preferred_cards )
	);

	// Discard puts the typed value back and hides the save bar.
	const title = row( page, 'Title' ).locator( 'input' );
	await title.fill( 'Not saved' );
	await page
		.locator( '.w-savebar.is-shown' )
		.waitFor( { state: 'attached', timeout: 10000 } );
	await page.locator( '[data-w-discard]' ).evaluate( ( b ) => b.click() );
	await page.waitForTimeout( 400 );
	record(
		( await title.inputValue() ) === 'Moneris' &&
			! ( await page.locator( '.w-savebar.is-shown' ).count() ),
		'admin page: Discard restores the field and hides the save bar'
	);

	// A text change through the page.
	await title.fill( 'Credit card' );
	await save( page );
	record(
		settings().title === 'Credit card',
		'admin page: a title change is saved',
		settings().title
	);
	await title.fill( 'Moneris' );
	await save( page );
	record(
		JSON.stringify( settings() ) === JSON.stringify( start ),
		'admin page: after every change and back, the option is as it started',
		JSON.stringify( settings() )
	);

	// Links into the page.
	await page.goto(
		ids.admin + 'admin.php?page=wc-settings&tab=checkout&section=moneris'
	);
	record(
		page.url().includes( 'page=wpheka-moneris' ),
		'old WooCommerce > Payments > Moneris link opens the new page',
		page.url()
	);
	await page.goto( ids.admin + 'plugins.php' );
	record(
		( await page
			.locator(
				'tr[data-slug="wc-moneris-payment-gateway"] a[href*="page=wpheka-moneris"]',
				{ hasText: 'Settings' }
			)
			.count() ) === 1,
		'plugins screen: Settings link opens the new page'
	);
	record(
		( await page
			.locator( '#adminmenu a[href*="page=wpheka-moneris"]' )
			.count() ) >= 1,
		'admin menu: WPHEKA > Moneris is listed'
	);
	await page.goto( ids.admin + 'admin.php?page=wc-settings&tab=checkout' );
	const manage = page.locator( 'a[href*="page=wpheka-moneris"]' );
	await manage
		.first()
		.waitFor( { timeout: 30000 } )
		.catch( () => {} );
	record(
		( await manage.count() ) >= 1,
		"WooCommerce Payments screen: Moneris's Manage link opens the new page"
	);

	// Screenshots for review at the four widths, from a fresh context: after the saves above,
	// headless Chromium can stop painting in this one.
	fs.mkdirSync( path.join( STATE, 'shots' ), { recursive: true } );
	const sctx = await browser.newContext( {
		viewport: { width: 1440, height: 900 },
	} );
	const spage = await sctx.newPage();
	spage.on( 'pageerror', ( e ) => {
		if ( ! SKIPPED( e.message ) ) {
			errors.push( e.message );
		}
	} );
	await login( spage, 'admin', 'password' );
	for ( const width of [ 1440, 1024, 782, 390 ] ) {
		await spage.setViewportSize( { width, height: 900 } );
		for ( const tab of [ 'settings', 'transactions', 'help' ] ) {
			await spage.goto( `${ PAGE }&tab=${ tab }` );
			await spage.screenshot( {
				path: path.join( STATE, 'shots', `${ tab }-${ width }.png` ),
				fullPage: true,
				animations: 'disabled',
			} );
		}
		const overflow = await spage.evaluate(
			() =>
				document.documentElement.scrollWidth -
				document.documentElement.clientWidth
		);
		record(
			overflow <= 0,
			`admin page: no sideways scroll at ${ width }px`,
			`${ overflow }px`
		);
	}
	await sctx.close();
	record(
		errors.length === 0,
		'admin page: no script errors as admin',
		errors.join( ' | ' )
	);

	// A shop manager can open and save the page, as on WooCommerce's settings.
	const mctx = await browser.newContext();
	const mpage = await mctx.newPage();
	await login( mpage, ...ids.manager );
	await mpage.goto( `${ PAGE }&tab=settings` );
	record(
		( await mpage.locator( '#wpheka-moneris-settings' ).count() ) === 1,
		'shop manager: sees the settings form'
	);
	await flip( mpage, 'Log gateway events' );
	await save( mpage );
	record(
		settings().logging === 'no',
		'shop manager: can save',
		settings().logging
	);
	await flip( mpage, 'Log gateway events' );
	await save( mpage );
	await mpage.goto(
		ids.admin + 'admin.php?page=wc-settings&tab=checkout&section=moneris'
	);
	record(
		mpage.url().includes( 'page=wpheka-moneris' ),
		'shop manager: old link opens the new page'
	);

	// A customer cannot.
	const cctx = await browser.newContext();
	const cpage = await cctx.newPage();
	await login( cpage, ...ids.customer ).catch( () => {} );
	const resp = await cpage.goto( `${ PAGE }&tab=settings` );
	record(
		( await cpage.locator( '#wpheka-moneris-settings' ).count() ) === 0 &&
			( resp.status() === 403 ||
				/not allowed/i.test( await cpage.content() ) ),
		'customer: cannot open the page',
		String( resp.status() )
	);

	record(
		JSON.stringify( settings() ) === JSON.stringify( start ),
		'admin.js: settings left as they started',
		JSON.stringify( settings() )
	);
	await browser.close();
	record( true, 'admin.js: ran to the end' );
} )();
