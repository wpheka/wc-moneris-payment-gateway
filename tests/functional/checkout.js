// Real Moneris sandbox payments in a browser, on the classic and the block checkout, then
// a previous-day refund through Moneris. Card 4242 4242 4242 4242, store5: no real money.
const { chromium } = require( 'playwright' );
const { SKIPPED, ids, record, php, guard } = require( './common' );

guard( 'checkout.js' );
const STORAGE = process.argv[ 2 ] || 'hpos';
const CARD = { number: '4242424242424242', expiry: '1230', cvc: '123' };

// Types the card key by key, as a customer does (the block form formats each key with
// Cleave), and checks what landed in each box before the order is placed.
async function typeCard( page ) {
	await page
		.locator( '#moneris-card-number' )
		.pressSequentially( CARD.number, { delay: 20 } );
	await page
		.locator( '#moneris-card-expiry' )
		.pressSequentially( CARD.expiry, { delay: 20 } );
	await page
		.locator( '#moneris-card-cvc' )
		.pressSequentially( CARD.cvc, { delay: 20 } );
	const typed = [
		await page.inputValue( '#moneris-card-number' ),
		await page.inputValue( '#moneris-card-expiry' ),
		await page.inputValue( '#moneris-card-cvc' ),
	];
	if (
		typed[ 0 ].replace( /\D/g, '' ) !== CARD.number ||
		typed[ 1 ].replace( /\D/g, '' ) !== CARD.expiry ||
		typed[ 2 ] !== CARD.cvc
	) {
		throw new Error( 'card fields hold ' + JSON.stringify( typed ) );
	}
}

// Every card field filled in one go, as browser card autofill does (the expiry with a
// four-digit year). The old Cleave.js form threw and left each field empty here.
async function autofillCard( page ) {
	await page.locator( '#moneris-card-number' ).fill( '4242 4242 4242 4242' );
	await page.locator( '#moneris-card-expiry' ).fill( '12/2030' );
	await page.locator( '#moneris-card-cvc' ).fill( CARD.cvc );
	const typed = [
		await page.inputValue( '#moneris-card-number' ),
		await page.inputValue( '#moneris-card-expiry' ),
		await page.inputValue( '#moneris-card-cvc' ),
	];
	if ( typed.join( '|' ) !== '4242 4242 4242 4242|12/30|123' ) {
		throw new Error( 'autofilled fields hold ' + JSON.stringify( typed ) );
	}
}

async function pay( page, mode ) {
	await page.goto( `${ ids.home }?add-to-cart=${ ids.product }` );
	if ( mode === 'classic' ) {
		await page.goto( ids.classic );
		const f = {
			billing_first_name: 'Zz',
			billing_last_name: 'Mon',
			billing_address_1: '1 Test St',
			billing_city: 'Winnipeg',
			billing_postcode: 'R3C 0A1',
			billing_phone: '2045550100',
			billing_email: 'zz-mon-guest@example.com',
		};
		for ( const [ k, v ] of Object.entries( f ) ) {
			if ( await page.locator( '#' + k ).count() ) {
				await page.fill( '#' + k, v );
			}
		}
		// Billing changes reload the payment section; type the card only after that settles.
		await page.locator( '#billing_email' ).press( 'Tab' );
		await page.waitForTimeout( 1500 );
		await page.waitForLoadState( 'networkidle' );
		await page
			.locator( '.blockUI' )
			.first()
			.waitFor( { state: 'detached', timeout: 30000 } )
			.catch( () => {} );
		await page
			.locator( '#payment_method_moneris' )
			.check( { force: true } );
		await page.waitForLoadState( 'networkidle' );
		await typeCard( page );
		await page.locator( '#place_order' ).click();
	} else {
		await page.goto( ids.block );
		await page.waitForSelector( '#email', { timeout: 60000 } );
		await page.fill( '#email', 'zz-mon-guest@example.com' );
		for ( const [ k, v ] of Object.entries( {
			first_name: 'Zz',
			last_name: 'Mon',
			address_1: '1 Test St',
			city: 'Winnipeg',
			postcode: 'R3C 0A1',
			phone: '2045550100',
		} ) ) {
			if ( await page.locator( `#billing-${ k }` ).count() ) {
				await page.fill( `#billing-${ k }`, v );
			}
		}
		await page.locator( 'input[value="moneris"]' ).check( { force: true } );
		await page.waitForLoadState( 'networkidle' );
		if ( mode === 'block-autofill' ) {
			await autofillCard( page );
		} else {
			await typeCard( page );
		}
		await page.waitForLoadState( 'networkidle' );
		await page
			.locator( '.wc-block-components-checkout-place-order-button' )
			.click();
	}
	await page.waitForURL( /order-received/, { timeout: 120000 } );
}

( async () => {
	const browser = await chromium.launch( {
		channel: process.env.PW_CHANNEL || undefined,
	} );
	for ( const mode of [ 'classic', 'block', 'block-autofill' ] ) {
		const label = `${
			mode === 'block-autofill'
				? 'block checkout, card autofilled'
				: mode + ' checkout'
		} (${ STORAGE })`;
		const ctx = await browser.newContext( {
			viewport: { width: 1280, height: 1000 },
		} );
		const page = await ctx.newPage();
		const errors = [];
		page.on( 'pageerror', ( e ) => {
			if ( ! SKIPPED( e.message ) ) {
				errors.push( e.message );
			}
		} );
		let ok = true;
		try {
			await pay( page, mode );
		} catch ( e ) {
			ok = false;
			const notice = (
				await page
					.locator(
						'.woocommerce-error, .wc-block-components-notice-banner, .woocommerce-NoticeGroup'
					)
					.allTextContents()
					.catch( () => [] )
			)
				.join( ' ' )
				.replace( /\s+/g, ' ' )
				.trim();
			await page
				.screenshot( {
					path: require( 'path' ).join(
						process.env.MONERIS_STATE,
						`fail-${ mode }-${ STORAGE }.png`
					),
					fullPage: true,
					animations: 'disabled',
				} )
				.catch( () => {} );
			record(
				false,
				`${ label }: reaches the order received page`,
				String( e ).split( '\n' )[ 0 ] +
					( notice ? ' | page says: ' + notice : '' )
			);
		}
		if ( ok ) {
			record( true, `${ label }: reaches the order received page` );
			php( 'order.php', 'paid', label );
			if ( mode === 'classic' ) {
				php( 'order.php', 'refund', label );
			}
		}
		record(
			errors.length === 0,
			`${ label }: no script errors`,
			errors.join( ' | ' )
		);
		await ctx.close();
	}
	php( 'order.php', 'logs', `gateway log (${ STORAGE })` );
	await browser.close();
	record( true, `checkout.js (${ STORAGE }): ran to the end` );
} )();
