/* Close tooltip on click-outside (mobile) */
jQuery( function ( $ ) {
    $( document ).on( 'click', function ( e ) {
        if ( ! $( e.target ).closest( '.gth-term' ).length ) {
            $( '.gth-term' ).removeClass( 'gth-active' );
        }
    } );
} );
