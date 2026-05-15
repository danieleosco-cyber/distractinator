/* global distractinator, jQuery */
( function ( $ ) {
	'use strict';

	var btn         = $( '#distractinator-btn' );
	var btnText     = $( '#distractinator-btn-text' );
	var spinner     = $( '#distractinator-spinner' );
	var counter     = $( '#distractinator-counter' );
	var submitToggle  = $( '#distractinator-submit-toggle' );
	var submitForm    = $( '#distractinator-submit-form' );
	var submitMsg     = $( '#dz-submit-msg' );
	var reportWrap    = $( '#distractinator-report-wrap' );
	var reportBtn     = $( '#distractinator-report-btn' );
	var reportMsg     = $( '#dz-report-msg' );
	var clickCount    = parseInt( sessionStorage.getItem( 'dz_clicks' ) || '0', 10 );
	var lastSiteId    = null;

	// Build redirect overlay once
	var overlay = $( '<div id="dz-redirect-overlay"><p class="dz-goto-text">Taking you somewhere useless…</p><p class="dz-goto-url"></p></div>' );
	$( 'body' ).append( overlay );

	function updateCounter() {
		if ( clickCount > 0 ) {
			counter.text( 'You\'ve visited ' + clickCount + ' distractinator site' + ( clickCount === 1 ? '' : 's' ) + ' this session.' );
		}
	}

	updateCounter();

	btn.on( 'click', function () {
		btn.prop( 'disabled', true );
		btnText.text( 'Finding a distraction…' );
		spinner.prop( 'hidden', false );

		$.ajax( {
			url:    distractinator.ajaxUrl,
			type:   'POST',
			data:   {
				action: 'distractinator_random',
				nonce:  distractinator.nonce,
			},
			success: function ( response ) {
				if ( response.success && response.data && response.data.url ) {
					var url = response.data.url;
					lastSiteId = response.data.id;

					overlay.find( '.dz-goto-url' ).text( url );
					overlay.addClass( 'active' );

					clickCount++;
					sessionStorage.setItem( 'dz_clicks', clickCount );

					setTimeout( function () {
						window.open( url, '_blank', 'noopener,noreferrer' );
						overlay.removeClass( 'active' );

						btn.prop( 'disabled', false );
						btnText.text( 'Take Me Somewhere Else!' );
						spinner.prop( 'hidden', true );
						updateCounter();

						// Show report link after first visit
						reportWrap.prop( 'hidden', false );
						reportMsg.prop( 'hidden', true ).text( '' );
						reportBtn.prop( 'disabled', false ).text( 'Report last link as dead ×' );
					}, 900 );
				} else {
					resetBtn( 'No sites found. Try again!' );
				}
			},
			error: function () {
				resetBtn( 'Something went wrong. Try again!' );
			},
		} );
	} );

	function resetBtn( msg ) {
		btn.prop( 'disabled', false );
		btnText.text( msg || 'Distract Me!' );
		spinner.prop( 'hidden', true );
		setTimeout( function () {
			btnText.text( 'Distract Me!' );
		}, 2000 );
	}

	// Report dead link
	reportBtn.on( 'click', function () {
		if ( ! lastSiteId ) return;
		reportBtn.prop( 'disabled', true ).text( 'Reporting…' );

		$.ajax( {
			url:  distractinator.ajaxUrl,
			type: 'POST',
			data: {
				action: 'distractinator_report',
				nonce:  distractinator.nonce,
				id:     lastSiteId,
			},
			success: function ( response ) {
				var msg = response.success ? ( response.data || 'Reported. Thanks!' ) : ( response.data || 'Could not report.' );
				reportMsg.text( msg ).prop( 'hidden', false );
				reportBtn.prop( 'hidden', true );
			},
			error: function () {
				reportMsg.text( 'Something went wrong.' ).prop( 'hidden', false );
				reportBtn.prop( 'disabled', false ).text( 'Report last link as dead ×' );
			},
		} );
	} );

	// Submission form toggle
	if ( submitToggle.length ) {
		submitToggle.on( 'click', function () {
			var isHidden = submitForm.prop( 'hidden' );
			submitForm.prop( 'hidden', ! isHidden );
			submitToggle.text( isHidden ? 'Never mind ↑' : 'Know a distractinator site? Submit it ↓' );
		} );

		$( '#distractinator-form' ).on( 'submit', function ( e ) {
			e.preventDefault();
			var url   = $( '#dz-url' ).val().trim();
			var title = $( '#dz-title' ).val().trim();

			if ( ! url ) {
				showSubmitMsg( 'Please enter a URL.', false );
				return;
			}

			$.ajax( {
				url:  distractinator.ajaxUrl,
				type: 'POST',
				data: {
					action: 'distractinator_submit',
					nonce:  distractinator.nonce,
					url:    url,
					title:  title,
				},
				success: function ( response ) {
					if ( response.success ) {
						showSubmitMsg( response.data, true );
						$( '#distractinator-form' )[ 0 ].reset();
					} else {
						showSubmitMsg( response.data || 'Submission failed.', false );
					}
				},
				error: function () {
					showSubmitMsg( 'Something went wrong. Please try again.', false );
				},
			} );
		} );

		function showSubmitMsg( msg, success ) {
			submitMsg
				.text( msg )
				.css( 'color', success ? '#7bc67e' : '#e94560' )
				.prop( 'hidden', false );
			if ( success ) {
				setTimeout( function () {
					submitMsg.prop( 'hidden', true );
					submitForm.prop( 'hidden', true );
					submitToggle.text( 'Know a distractinator site? Submit it ↓' );
				}, 3000 );
			}
		}
	}

} )( jQuery );
