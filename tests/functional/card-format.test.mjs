// The block checkout's card formatting (resources/js/frontend/card-format.mjs): what lands in
// each box after typing, pasting or autofill, and what the server then reads.
// Run by run.sh:  node tests/functional/card-format.test.mjs <results file>
import { appendFileSync } from 'fs';
import {
	formatCardNumber,
	formatExpiry,
	formatCvc,
} from '../../resources/js/frontend/card-format.mjs';

const results = process.argv[ 2 ];
let failed = 0;
const check = ( name, got, want ) => {
	const ok = got === want;
	if ( ! ok ) {
		failed++;
	}
	const line = `${ ok ? 'PASS' : 'FAIL' }|card format: ${ name }${
		ok
			? ''
			: ` -- got ${ JSON.stringify( got ) }, want ${ JSON.stringify(
					want
			  ) }`
	}`;
	console.log( '  ' + line );
	if ( results ) {
		appendFileSync( results, line + '\n' );
	}
};

// Card numbers.
check(
	'Visa typed',
	formatCardNumber( '4242424242424242' ),
	'4242 4242 4242 4242'
);
check( 'Visa as typed so far', formatCardNumber( '42424' ), '4242 4' );
check(
	'autofill with spaces',
	formatCardNumber( '4242 4242 4242 4242' ),
	'4242 4242 4242 4242'
);
check(
	'paste with dashes',
	formatCardNumber( '4242-4242-4242-4242' ),
	'4242 4242 4242 4242'
);
check(
	'American Express 4-6-5',
	formatCardNumber( '378282246310005' ),
	'3782 822463 10005'
);
check(
	'American Express stops at 15',
	formatCardNumber( '3782822463100059' ),
	'3782 822463 10005'
);
check(
	'19-digit card',
	formatCardNumber( '6011000990139424123' ),
	'6011 0009 9013 9424 123'
);
check( 'letters dropped', formatCardNumber( '4242abcd4242' ), '4242 4242' );
check( 'empty', formatCardNumber( '' ), '' );
check( 'undefined', formatCardNumber( undefined ), '' );

// Expiry.
check( 'expiry typed', formatExpiry( '1230' ), '12/30' );
check( 'expiry autofill MM/YY', formatExpiry( '12/30' ), '12/30' );
check( 'expiry autofill MM/YYYY', formatExpiry( '12/2030' ), '12/30' );
check( 'expiry autofill MMYYYY', formatExpiry( '122030' ), '12/30' );
check( 'expiry autofill M/YY', formatExpiry( '4/30' ), '04/30' );
check( 'expiry autofill M/YYYY', formatExpiry( '4/2030' ), '04/30' );
check( 'expiry spaces', formatExpiry( ' 12 / 30 ' ), '12/30' );
check( 'month 4 becomes 04', formatExpiry( '4' ), '04' );
check( 'month 1 waits for the second digit', formatExpiry( '1' ), '1' );
check( 'month 13 kept to 12', formatExpiry( '1330' ), '12/30' );
check( 'month 00 becomes 01', formatExpiry( '0030' ), '01/30' );
check( 'deleting back to the month', formatExpiry( '12/' ), '12' );
check( 'expiry too long', formatExpiry( '123099' ), '12/30' );
check( 'expiry empty', formatExpiry( '' ), '' );

// CVC.
check( 'CVC 3 digits', formatCvc( '123' ), '123' );
check( 'CVC 4 digits', formatCvc( '1234' ), '1234' );
check( 'CVC cut at 4', formatCvc( '12345' ), '1234' );
check( 'CVC letters dropped', formatCvc( '1a2b3' ), '123' );

const end = 'PASS|card-format.test.mjs: ran to the end';
console.log( '  ' + end );
if ( results ) {
	appendFileSync( results, end + '\n' );
}
process.exit( failed ? 1 : 0 );
