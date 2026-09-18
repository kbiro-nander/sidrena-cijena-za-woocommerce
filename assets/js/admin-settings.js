/**
 * Sidrena cijena – settings page: category-override repeater (vanilla JS, no jQuery).
 */
( function () {
	'use strict';

	function reindex( container ) {
		var rows = container.querySelectorAll( '.scwc-overrides__rows > .scwc-overrides__row' );
		Array.prototype.forEach.call( rows, function ( row, i ) {
			row.setAttribute( 'data-index', String( i ) );
			Array.prototype.forEach.call( row.querySelectorAll( '[name]' ), function ( el ) {
				el.name = el.name.replace( /\[(\d+|__INDEX__)\]\[(term_ids|date)\]/, '[' + i + '][$2]' );
			} );
		} );
	}

	function addRow( container, date ) {
		var template = container.querySelector( '.scwc-overrides__template' );
		var rows     = container.querySelector( '.scwc-overrides__rows' );
		if ( ! template || ! rows ) {
			return;
		}
		var html    = template.innerHTML.replace( /__INDEX__/g, String( rows.children.length ) );
		var wrapper = document.createElement( 'div' );
		wrapper.innerHTML = html.trim();
		var row = wrapper.firstElementChild;
		if ( ! row ) {
			return;
		}
		if ( date ) {
			var dateInput = row.querySelector( '.scwc-overrides__date' );
			if ( dateInput ) {
				dateInput.value = date;
			}
		}
		rows.appendChild( row );
		reindex( container );
		var select = row.querySelector( 'select' );
		if ( select ) {
			select.focus();
		}
	}

	function init( container ) {
		container.addEventListener( 'click', function ( event ) {
			var target = event.target;
			if ( ! ( target instanceof Element ) ) {
				return;
			}
			if ( target.closest( '.scwc-overrides__add' ) ) {
				event.preventDefault();
				addRow( container, '' );
			} else if ( target.closest( '.scwc-overrides__add-fmcg' ) ) {
				event.preventDefault();
				addRow( container, container.getAttribute( 'data-fmcg-date' ) || '' );
			} else if ( target.closest( '.scwc-overrides__remove' ) ) {
				event.preventDefault();
				var row = target.closest( '.scwc-overrides__row' );
				if ( row && row.parentNode ) {
					row.parentNode.removeChild( row );
					reindex( container );
				}
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.scwc-overrides' ), init );
	} );
}() );
