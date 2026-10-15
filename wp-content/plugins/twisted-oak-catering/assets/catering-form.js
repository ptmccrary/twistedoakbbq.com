/**
 * Twisted Oak catering form — progressive enhancement.
 *
 * The server renders all three steps inside one <form> that posts to
 * admin-post.php. This script turns it into a one-step-at-a-time wizard
 * (same behaviour as the old Drupal module) and submits via the REST route.
 */
( function () {
	'use strict';

	var config = window.twistedoakCatering || {};
	var i18n = config.i18n || {};
	var TOTAL = 3;

	function t( key, fallback ) {
		return i18n[ key ] || fallback;
	}

	function esc( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function init( root ) {
		var form = root.querySelector( 'form' );
		if ( ! form || root.dataset.enhanced ) {
			return;
		}
		root.dataset.enhanced = '1';
		root.classList.add( 'is-enhanced' );
		form.noValidate = true;

		var steps = Array.prototype.slice.call( form.querySelectorAll( '.catering-step[data-step]' ) );
		var dots = Array.prototype.slice.call( form.querySelectorAll( '.catering-progress__dot' ) );
		var progress = form.querySelector( '.catering-progress' );
		var stepLabel = form.querySelector( '.catering-progress__label' );
		var summary = form.querySelector( '.catering-error--summary' );
		var packageHeading = form.querySelector( '[data-package-heading]' );
		var state = { step: 1, package: null };

		Array.prototype.forEach.call( form.querySelectorAll( '[data-action][hidden]' ), function ( btn ) {
			btn.hidden = false;
		} );
		Array.prototype.forEach.call( form.querySelectorAll( '[data-nojs-only]' ), function ( node ) {
			node.hidden = true;
		} );
		if ( progress ) {
			progress.hidden = false;
		}

		function selectedPackage() {
			return form.querySelector( 'input[name="package"]:checked' );
		}

		function setError( step, message ) {
			steps.forEach( function ( s ) {
				var el = s.querySelector( '[data-error]' );
				if ( ! el ) {
					return;
				}
				var show = message && Number( s.dataset.step ) === step;
				el.textContent = show ? message : '';
				el.hidden = ! show;
				if ( show ) {
					el.setAttribute( 'role', 'alert' );
				}
			} );
		}

		function clearErrors() {
			setError( 0, '' );
			if ( summary ) {
				summary.hidden = true;
			}
		}

		/** Show/hide premium proteins for the chosen package; unchecks hidden ones. */
		function applyPackage( radio ) {
			var premium = !! ( radio && radio.hasAttribute( 'data-premium' ) );
			Array.prototype.forEach.call( form.querySelectorAll( '[data-group="meats"] [data-premium]' ), function ( label ) {
				label.hidden = ! premium;
				if ( ! premium ) {
					var input = label.querySelector( 'input' );
					if ( input ) {
						input.checked = false;
					}
				}
			} );
			if ( packageHeading && radio ) {
				packageHeading.textContent = radio.value;
			}
		}

		function resetSelections() {
			Array.prototype.forEach.call(
				form.querySelectorAll( '[data-step="2"] input[type="checkbox"]' ),
				function ( input ) {
					input.checked = false;
				}
			);
		}

		function showStep( n, moveFocus ) {
			state.step = n;
			steps.forEach( function ( s ) {
				s.hidden = Number( s.dataset.step ) !== n;
			} );
			dots.forEach( function ( dot, i ) {
				dot.classList.toggle( 'catering-progress__dot--active', i === n - 1 );
			} );
			if ( stepLabel ) {
				stepLabel.textContent = t( 'stepOf', 'Step %1$s of %2$s' ).replace( '%1$s', n ).replace( '%2$s', TOTAL );
			}
			if ( moveFocus ) {
				var heading = steps[ n - 1 ] && steps[ n - 1 ].querySelector( '.catering-step__heading' );
				if ( heading ) {
					heading.setAttribute( 'tabindex', '-1' );
					heading.focus( { preventScroll: true } );
					root.scrollIntoView( { block: 'start', behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' } );
				}
			}
		}

		function next() {
			clearErrors();
			if ( state.step === 1 ) {
				var radio = selectedPackage();
				if ( ! radio ) {
					setError( 1, t( 'selectPackage', 'Please select a package to continue.' ) );
					return;
				}
				if ( state.package !== null && radio.value !== state.package ) {
					resetSelections();
				}
				state.package = radio.value;
				applyPackage( radio );
			}
			showStep( Math.min( state.step + 1, TOTAL ), true );
		}

		function back() {
			clearErrors();
			showStep( Math.max( state.step - 1, 1 ), true );
		}

		function renderSuccess( message ) {
			root.innerHTML =
				'<div class="catering-success" role="status" tabindex="-1">' +
				'<p class="catering-success__heading">' + esc( t( 'thanks', 'Thank you!' ) ) + '</p>' +
				'<p>' + esc( message ) + '</p>' +
				'</div>';
			var box = root.firstChild;
			if ( box && box.focus ) {
				box.focus();
			}
		}

		function stepForCode( code ) {
			if ( code === 'package' ) {
				return 1;
			}
			return code === 'premium' ? 2 : 3;
		}

		function submit( submitBtn ) {
			clearErrors();
			var name = form.elements.name;
			var email = form.elements.email;
			if ( ! name.value.trim() || ! email.value.trim() ) {
				setError( 3, t( 'required', 'Please fill in your name and email.' ) );
				( name.value.trim() ? email : name ).focus();
				return;
			}
			if ( email.validity && ! email.validity.valid ) {
				setError( 3, t( 'invalidEmail', 'Please enter a valid email address.' ) );
				email.focus();
				return;
			}

			var label = submitBtn ? submitBtn.textContent : '';
			if ( submitBtn ) {
				submitBtn.disabled = true;
				submitBtn.textContent = t( 'sending', 'Sending…' );
			}
			root.setAttribute( 'aria-busy', 'true' );

			var headers = { Accept: 'application/json' };
			if ( config.restNonce ) {
				headers[ 'X-WP-Nonce' ] = config.restNonce;
			}

			fetch( config.restUrl || '/wp-json/twisted-oak/v1/catering', {
				method: 'POST',
				credentials: 'same-origin',
				headers: headers,
				body: new FormData( form ),
			} )
				.then( function ( res ) {
					return res.json().catch( function () {
						return {};
					} );
				} )
				.then( function ( json ) {
					if ( json && json.success ) {
						renderSuccess( json.message || '' );
						return;
					}
					var step = stepForCode( json && json.code );
					showStep( step, false );
					setError( step, ( json && json.error ) || t( 'generic', 'Something went wrong. Please try again.' ) );
				} )
				.catch( function () {
					setError( 3, t( 'network', 'Network error. Please check your connection and try again.' ) );
				} )
				.then( function () {
					root.removeAttribute( 'aria-busy' );
					if ( submitBtn && submitBtn.isConnected ) {
						submitBtn.disabled = false;
						submitBtn.textContent = label;
					}
				} );
		}

		form.addEventListener( 'click', function ( event ) {
			var btn = event.target.closest( '[data-action]' );
			if ( ! btn || ! form.contains( btn ) ) {
				return;
			}
			if ( btn.dataset.action === 'next' ) {
				event.preventDefault();
				next();
			} else if ( btn.dataset.action === 'back' ) {
				event.preventDefault();
				back();
			}
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			if ( state.step < TOTAL ) {
				// Enter key inside an earlier step behaves like "Next".
				next();
				return;
			}
			submit( form.querySelector( '[data-action="submit"]' ) );
		} );

		// Initial state (honours a server-side error from the no-JS fallback).
		var initialRadio = selectedPackage();
		if ( initialRadio ) {
			state.package = initialRadio.value;
		}
		applyPackage( initialRadio );
		var initial = Number( root.dataset.initialStep ) || 1;
		if ( initial > 1 && ! initialRadio ) {
			initial = 1;
		}
		showStep( initial, false );
	}

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-catering-form]' ), init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
