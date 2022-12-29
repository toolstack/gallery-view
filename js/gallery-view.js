(function($) {

	'use strict';

	jQuery(document).ready(function($) {

		jQuery('#gv_show_date_switch').click(function() {

				var url = new URL( window.location.href );

				if( $('#gv_show_date_switch').prop( 'checked' ) ) {
					url.searchParams.set( 'gv_show_date', '1' );
				} else {
					url.searchParams.set( 'gv_show_date', '' );
	  			}

	  			window.location.href = url.toString();
			});

		jQuery('#gv_show_title_switch').click(function() {

				var url = new URL( window.location.href );

				if( $('#gv_show_title_switch').prop( 'checked' ) ) {
					url.searchParams.set( 'gv_show_title', '1' );
				} else {
					url.searchParams.set( 'gv_show_title', '' );
	  			}

	  			window.location.href = url.toString();
			});

	});

})(jQuery);