const MINDEVENTS_PREPEND = 'mindevents_';


(function( root, $, undefined ) {
	"use strict";

	$(function () {

		function closeAllEventMeta() {
			$("tr.meta-container").each(function() {
				$(this).removeClass('show').find('.eventMeta').html('');
			});
		}


		$(document).on('click', '.event-meta-close', function (event) {
			closeAllEventMeta();
		});


		$(document).on('click', '.sub-event-toggle', function (event) {
			closeAllEventMeta();
			var eventid = $(this).data('eventid');
			var container = $(this).closest('tr').next().addClass('show');
			var metaContainer = container.find('.eventMeta');

			metaContainer.html('<div class="la-ball-fall"><div></div><div></div><div></div></div>');

			$.ajax({
				url : mindeventsSettings.ajax_url,
				type : 'post',
				data : {
					action : MINDEVENTS_PREPEND + 'get_event_meta_html',
					eventid : eventid
				},
				success: function(response) {
					metaContainer.html(response.data.html);
					// console.log(response);
				},
				error: function (response) {
					console.log('An error occurred.');
					console.log(response);
				},
			});


		});



		$(document).on('click', '.calendar-nav .calnav', function (event) {
			event.preventDefault();
			var eventsCalendar = $('#publicCalendar');
			var calendarTable = $('#mindEventCalendar');
			var month = calendarTable.data('month');
			var year = calendarTable.data('year');
			var direction = $(this).data('dir');
			var category = $(this).data('cat');

			var height = eventsCalendar.height();
			var width = eventsCalendar.width();
			eventsCalendar.height(height).width(width);
			eventsCalendar.html('<div class="la-ball-fall"><div></div><div></div><div></div></div>');

			$.ajax({
				url : mindeventsSettings.ajax_url,
				type : 'post',
				data : {
					action : MINDEVENTS_PREPEND + 'move_pub_calendar',
					direction : direction,
					month : month,
					year : year,
					category : category,
					eventid : mindeventsSettings.post_id
				},
				success: function(response) {
					eventsCalendar.attr('style', false);
					eventsCalendar.html(response.html);

				},
				error: function (response) {
					console.log('An error occurred.');
					console.log(response);
				},
			});

		})

		$(document).on('click', '.calendar-nav .archive-calnav', function (event) {
			event.preventDefault();
			var eventsCalendar = $('#archiveCalendar');
			var calendarTable = $('#mindEventCalendar');
			var month = calendarTable.data('month');
			var year = calendarTable.data('year');
			var direction = $(this).data('dir');

			var height = eventsCalendar.height();
			var width = eventsCalendar.width();
			eventsCalendar.height(height).width(width);
			eventsCalendar.html('<div class="la-ball-fall"><div></div><div></div><div></div></div>');

			$.ajax({
				url : mindeventsSettings.ajax_url,
				type : 'post',
				data : {
					action : MINDEVENTS_PREPEND + 'move_archive_calendar',
					direction : direction,
					month : month,
					year : year,
				},
				success: function(response) {
					eventsCalendar.attr('style', false);
					eventsCalendar.html(response.html);

				},
				error: function (response) {
					console.log('An error occurred.');
					console.log(response);
				},
			});

		})


		$(document).on('click', 'button.mindevents-add-to-cart', function (event) {
			event.preventDefault();
			var button = $(this);
			var buttonParent = button.parent();
			var product_id = $(this).data('product_id');
			var quantity = $(this).data('quantity');
			var event_date = $(this).data('event_date');


			$.ajax({
				url : mindeventsSettings.ajax_url,
				type : 'post',
				data : {
					action : MINDEVENTS_PREPEND + 'add_woo_product_to_cart',
					product_id : product_id,
					quantity : quantity,
					event_date : event_date
				},
				beforeSend: function() {
					$('.go-to-cart').remove();
					button.prop('disabled', true);
				},
				afterSend: function() {
					
				},
				success: function(response) {
					
					if(response.success) {
						button.prop('disabled', false);
						//get current style string of button
						var style = button.attr('style');
						//hide button
						button.hide();
						buttonParent.append('<a class="button go-to-cart" style="' + style + '" href="' + mindeventsSettings.cart_url + '">Success! Go to Cart.</a>');
						//increment cart icon count
						$('.cart-contents-count').find('svg').attr('data-icon', 'circle-' + response.data.cart_count);
				
					} else {
						console.log(response.data);
						response.data.forEach(function(item) {
							$('#cartErrorContainer').append('<p>' + item.notice + '</p>');

						});

						button.hide();
						scrollIntoView('#cartErrorContainer');
					}
					
				},
				error: function (response) {
					console.log('An error occurred.');
					console.log(response);
				},
			});


		});

  });

  function scrollIntoView($target) {
	var target = $($target);
    if (target.length)
        {
            var top = target.offset().top + -300;
            $('html,body').animate({scrollTop: top}, 1000);
            return false;
        }
  }

} ( this, jQuery ));
