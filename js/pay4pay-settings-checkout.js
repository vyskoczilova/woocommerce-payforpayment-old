(function($){
	function change_handler(){
		function disable(i,elem) {
			var $el = $(this).closest('tr');
			if ( ! $el.length )
				$el = $(this);
			if ( $el.length )
				$el.hide(0);//.attr('disabled','disabled');
		}
		function enable(i,elem) {
			var $el = $(this).closest('tr');
			if ( ! $el.length )
				$el = $(this);
			if ( $el.length )
				$el.show(0);
		}

		var id = $(this).attr('data-reference-name'), 
			val;
		if ( !! id ) {
			if ( $(this).is('[type="checkbox"]') )
				val = $(this).is(':checked');
			else
				val = $(this).val()*1;
			if ( !!val ) {
				$('[data-dependency-notzero="'+id+'"],.dependency-notzero-'+id).each(enable);
				$('[data-dependency-zero="'+id+'"],.dependency-zero-'+id).each(disable);
			} else if ( val == 0 ) {
				$('[data-dependency-notzero="'+id+'"],.dependency-notzero-'+id).each(disable);
				$('[data-dependency-zero="'+id+'"],.dependency-zero-'+id).each(enable);
			}
		}
	
	}
	$(document).on( 'click change keyup' , '[data-setchangehandler="1"]' , change_handler );
	
	$(document).ready(function() { $('[data-setchangehandler="1"]').each(change_handler); });
})(jQuery);