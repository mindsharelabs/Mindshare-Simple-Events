(function( root, $, undefined ) {
	"use strict";

	$(function () {

		function closeAllEventMeta() {
			$("tr.meta-container").each(function() {
				$(this).removeClass('show').find('.eventMeta').html('');
			});
		}

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
					action : 'mindevents_get_event_meta_html',
					eventid : eventid
				},
				success: function(response) {
					metaContainer.html(response.data.html);
					console.log(response);
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

			var height = eventsCalendar.height();
			var width = eventsCalendar.width();
			eventsCalendar.height(height).width(width);
			eventsCalendar.html('<div class="la-ball-fall"><div></div><div></div><div></div></div>');

			$.ajax({
				url : mindeventsSettings.ajax_url,
				type : 'post',
				data : {
					action : 'mindevents_move_pub_calendar',
					direction : direction,
					month : month,
					year : year,
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


  });

} ( this, jQuery ));
