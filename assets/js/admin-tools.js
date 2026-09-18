/* global scwcTools */
/**
 * Sidrena cijena – Tools page: batched snapshot, CSV import preview/apply, CSV export.
 */
( function () {
	'use strict';

	if ( typeof scwcTools === 'undefined' ) {
		return;
	}

	var i18n = scwcTools.i18n || {};

	function $( id ) {
		return document.getElementById( id );
	}

	function show( el, visible ) {
		if ( el ) {
			el.style.display = visible ? '' : 'none';
		}
	}

	function text( el, value ) {
		if ( el ) {
			el.textContent = value;
		}
	}

	function sprintf( format ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( format ).replace( /%(\d+\$)?d/g, function ( match, pos ) {
			var index = pos ? parseInt( pos, 10 ) - 1 : i++;
			return String( args[ index ] );
		} );
	}

	/**
	 * One AJAX step. `args` is a plain object; `file` an optional File.
	 */
	function step( tool, page, args, file ) {
		var body = new FormData();
		body.append( 'action', scwcTools.action || 'scwc_tool_step' );
		body.append( 'nonce', scwcTools.nonce );
		body.append( 'tool', tool );
		body.append( 'page', String( page || 1 ) );
		Object.keys( args || {} ).forEach( function ( key ) {
			body.append( 'args[' + key + ']', args[ key ] );
		} );
		if ( file ) {
			body.append( 'file', file );
		}
		return fetch( scwcTools.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json().catch( function () {
					throw new Error( i18n.networkError || 'Network error' );
				} );
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					var message = json && json.data && json.data.message ? json.data.message : ( i18n.networkError || 'Error' );
					throw new Error( message );
				}
				return json.data;
			} );
	}

	function formArgs( form ) {
		var args = {};
		var data = new FormData( form );
		data.forEach( function ( value, key ) {
			if ( key !== 'file' ) {
				args[ key ] = value;
			}
		} );
		return args;
	}

	function appendLog( pre, line ) {
		show( pre, true );
		pre.textContent += line + '\n';
		pre.scrollTop = pre.scrollHeight;
	}

	function escapeHtml( value ) {
		var div = document.createElement( 'div' );
		div.textContent = value === null || value === undefined ? '' : String( value );
		return div.innerHTML;
	}

	function addRow( tbody, cells ) {
		var tr = document.createElement( 'tr' );
		tr.innerHTML = cells.map( function ( c ) {
			return '<td>' + escapeHtml( c ) + '</td>';
		} ).join( '' );
		tbody.appendChild( tr );
	}

	/* ---------- Snapshot ---------- */

	var snapshotForm = $( 'scwc-snapshot-form' );
	if ( snapshotForm ) {
		var typeSelect = $( 'scwc-snapshot-type' );
		var dateInput = $( 'scwc-snapshot-date' );
		var typeDates = {};
		try {
			typeDates = JSON.parse( snapshotForm.getAttribute( 'data-type-dates' ) || '{}' );
		} catch ( e ) {
			typeDates = {};
		}
		if ( typeSelect && dateInput ) {
			typeSelect.addEventListener( 'change', function () {
				dateInput.value = typeDates[ typeSelect.value ] || '';
			} );
		}

		snapshotForm.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var args = formArgs( snapshotForm );
			var dryRun = args.dry_run === '1';
			if ( ! dryRun && ! window.confirm( i18n.confirmSnapshot || 'OK?' ) ) {
				return;
			}
			var button = $( 'scwc-snapshot-run' );
			var progress = $( 'scwc-snapshot-progress' );
			var log = $( 'scwc-snapshot-log' );
			var samplesTable = $( 'scwc-snapshot-samples' );
			var tbody = samplesTable.querySelector( 'tbody' );
			var totals = { processed: 0, written: 0, skipped_existing: 0, skipped_on_sale: 0, marked_na: 0, skipped_no_price: 0 };
			var sampleCount = 0;

			button.disabled = true;
			log.textContent = '';
			tbody.innerHTML = '';
			show( samplesTable, false );
			show( progress, true );
			progress.removeAttribute( 'value' );
			appendLog( log, i18n.starting || '…' );

			function run( page ) {
				return step( 'snapshot', page, args ).then( function ( data ) {
					appendLog( log, data.log );
					Object.keys( totals ).forEach( function ( key ) {
						totals[ key ] += data.result[ key ] || 0;
					} );
					( data.result.samples || [] ).forEach( function ( s ) {
						if ( sampleCount < 20 ) {
							sampleCount++;
							show( samplesTable, true );
							addRow( tbody, [ s.id, s.sku, s.name, s.regular, ( i18n.actions && i18n.actions[ s.action ] ) || s.action ] );
						}
					} );
					if ( ! data.done ) {
						return run( data.next_page );
					}
					progress.value = 100;
					appendLog( log, ( i18n.done || 'Done.' ) + ' ' + sprintf(
						'%d / %d / %d / %d / %d / %d',
						totals.processed, totals.written, totals.skipped_existing, totals.skipped_on_sale, totals.skipped_no_price, totals.marked_na
					) );
				} );
			}

			run( 1 ).catch( function ( err ) {
				appendLog( log, ( i18n.error || 'Error:' ) + ' ' + err.message );
				progress.value = 0;
			} ).then( function () {
				button.disabled = false;
			} );
		} );
	}

	/* ---------- Import ---------- */

	var importForm = $( 'scwc-import-form' );
	if ( importForm ) {
		var applyButton = $( 'scwc-import-apply' );
		var tokenInput = $( 'scwc-import-token' );
		var summary = $( 'scwc-import-summary' );
		var errorsBox = $( 'scwc-import-errors' );
		var rowsTable = $( 'scwc-import-rows' );
		var resultBox = $( 'scwc-import-result' );

		importForm.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var fileInput = $( 'scwc-import-file' );
			var file = fileInput && fileInput.files && fileInput.files[ 0 ];
			if ( ! file ) {
				window.alert( i18n.chooseFile || 'Choose a file.' );
				return;
			}
			var previewButton = $( 'scwc-import-preview' );
			previewButton.disabled = true;
			applyButton.disabled = true;
			tokenInput.value = '';
			show( resultBox, false );
			show( errorsBox, false );
			show( rowsTable, false );

			step( 'import_preview', 1, formArgs( importForm ), file ).then( function ( data ) {
				tokenInput.value = data.token;
				text( summary, data.total + ' ' + ( i18n.rowsValid ? 'redaka; ' : '' ) + data.valid + ' ' + ( i18n.rowsValid || 'valid' ) + ', ' + data.invalid + ' ' + ( i18n.rowsInvalid || 'invalid' ) + ', ' + data.unmatched + ' ' + ( i18n.rowsUnmatched || 'unmatched' ) );
				show( summary, true );

				var list = errorsBox.querySelector( 'ul' );
				list.innerHTML = '';
				( data.errors || [] ).forEach( function ( message ) {
					var li = document.createElement( 'li' );
					li.textContent = message;
					list.appendChild( li );
				} );
				show( errorsBox, ( data.errors || [] ).length > 0 );

				var tbody = rowsTable.querySelector( 'tbody' );
				tbody.innerHTML = '';
				( data.rows || [] ).forEach( function ( row ) {
					addRow( tbody, [ row.line, row.sku, row.amount === null ? '' : row.amount, row.date || '', row.type, row.na ? '1' : '', row.error || 'OK' ] );
				} );
				show( rowsTable, ( data.rows || [] ).length > 0 );

				applyButton.disabled = data.fatal || data.valid === 0;
			} ).catch( function ( err ) {
				text( summary, ( i18n.error || 'Error:' ) + ' ' + err.message );
				show( summary, true );
			} ).then( function () {
				previewButton.disabled = false;
			} );
		} );

		applyButton.addEventListener( 'click', function () {
			if ( ! tokenInput.value || ! window.confirm( i18n.confirmImport || 'OK?' ) ) {
				return;
			}
			applyButton.disabled = true;
			step( 'import_apply', 1, { token: tokenInput.value } ).then( function ( data ) {
				text( resultBox, sprintf( i18n.imported || '%1$d / %2$d / %3$d', data.updated, data.marked_na, data.skipped ) );
				show( resultBox, true );
				tokenInput.value = '';
				var list = errorsBox.querySelector( 'ul' );
				list.innerHTML = '';
				( data.errors || [] ).forEach( function ( message ) {
					var li = document.createElement( 'li' );
					li.textContent = message;
					list.appendChild( li );
				} );
				show( errorsBox, ( data.errors || [] ).length > 0 );
			} ).catch( function ( err ) {
				text( resultBox, ( i18n.error || 'Error:' ) + ' ' + err.message );
				show( resultBox, true );
				applyButton.disabled = false;
			} );
		} );
	}

	/* ---------- Export ---------- */

	var exportForm = $( 'scwc-export-form' );
	if ( exportForm ) {
		exportForm.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var button = $( 'scwc-export-run' );
			var status = $( 'scwc-export-status' );
			button.disabled = true;
			text( status, i18n.starting || '…' );
			show( status, true );

			step( 'export', 1, formArgs( exportForm ) ).then( function ( data ) {
				var blob = new Blob( [ data.csv ], { type: 'text/csv;charset=utf-8' } );
				var url = URL.createObjectURL( blob );
				var a = document.createElement( 'a' );
				a.href = url;
				a.download = data.filename;
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				setTimeout( function () {
					URL.revokeObjectURL( url );
				}, 1000 );
				text( status, sprintf( i18n.exported || '%d', data.rows ) );
			} ).catch( function ( err ) {
				text( status, ( i18n.error || 'Error:' ) + ' ' + err.message );
			} ).then( function () {
				button.disabled = false;
			} );
		} );
	}
}() );
