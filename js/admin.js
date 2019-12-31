(function( root, $, undefined ) {
	"use strict";

	$(function () {

		function initTimePicker() {
			$('.timepicker').timepicker({
		    timeFormat: 'h:mm p',
		    // interval: 15,
		    minTime: '12:00 AM',
		    maxTime: '11:59 PM',
		    // defaultTime: '7:00 pm',
		    // startTime: '10:00',
		    dynamic: true,
		    dropdown: false,
		    scrollbar: true
			});
		}
		initTimePicker();



		function initDatePicker() {
			$( ".datepicker" ).datepicker({
			  'dateFormat': 'yy-m-d'
			});
		}
		initDatePicker();

		$(document).on('change', 'input', function() {
			$(this).removeClass('validate');
		})

		$(document).on('click', '.time-block .remove', function (event) {
			$(this).parent('.time-block').fadeOut(500, function() {
				$(this).remove();
			})
		});








		$(document).on('click', '.add-event-occurrence', function (event) {
	    event.preventDefault();
	    var i = $('.time-block').length + 1;
	    $('.time-block').first().clone().find("input").attr('id', function(idx, attrVal) {
	        return attrVal + i;  // change the id
	    }).attr('name', function(idx, attrVal) {
	        return attrVal + i;  // change the name
	    }).removeAttr('checked').end().find('label').attr('for', function(idx, attrVal) {
	        return attrVal + i; // change the for
	    }).end().find('textarea').attr('id', function(idx, attrVal) {
					return attrVal + i; //change id
			}).attr('name', function(idx, attrVal) {
					return attrVal + i; //change the name
			}).removeAttr('checked').end().append('<div class="remove"><i class="fas fa-times"></i></div>').insertBefore(this);
			initTimePicker();
		});




    $(document).on('click', '.calendar-day', function (event) {

			var emptyInputs = $("#defaultEventMeta").find('input[type="text"].required').filter(function() {
				return $(this).val() == "";
			});
	    if (emptyInputs.length) {
        emptyInputs.each(function() {
					$(this).addClass('validate');
				});
	    } else {
				event.preventDefault();
				var thisDay = $(this);
				var occurrence = thisDay.siblings('.event');
				var errorBox = $('#errorBox');


				var meta = {};
				$("#defaultEventMeta .form-section > input, #defaultEventMeta .form-section > textarea").each(function() {
					meta[$(this).attr("name")] = $(this).val();
				});


				thisDay.addClass('loading').append('<div class="la-ball-fall"><div></div><div></div><div></div></div>');
				var date = $(this).attr('datetime');
	  		$.ajax({
	  			url : mindeventsSettings.ajax_url,
	  			type : 'post',
	  			data : {
	  				action : 'mindevents_selectday',
	  				eventid : mindeventsSettings.post_id,
						date : date,
						meta : meta,
						occurrence : occurrence.length
	  			},
	  			success: function(response) {
						thisDay.removeClass('loading');
						thisDay.find('.la-ball-fall').remove();
	          if(response.html) {
							thisDay.addClass('selected');
							setTimeout(function() {
								thisDay.removeClass('selected');
							}, 400);
							thisDay.attr('event', 'true');
							thisDay.after(response.html);
	          }
						if(response.errors.length > 0) {
							thisDay.addClass('whoops');
							setTimeout(function() {
								thisDay.removeClass('whoops');
							}, 400);

							var items = $("#errorBox > span").length;
							$.each(response.errors, function( index, value ) {
								var i = items + 1;
								errorBox.prepend('<span class="error-item-'+ i +'">' + value + '</span>').addClass('show');
								setTimeout(function() {
								  $('.error-item-'+ i +'').fadeOut(400, function() {
										$(this).remove();
									});
								}, 3000);
							});
	          }
	  			},
	  			error: function (response) {
	  				console.log('An error occurred.');
	  				console.log(response);
	  			},
	  		});
			}
  	})







		$(document).on('click', '.calendar-nav .calnav', function (event) {

			event.preventDefault();
			var eventsCalendar = $('#eventsCalendar');
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
					action : 'mindevents_movecalendar',
					direction : direction,
					month : month,
					year : year,
					eventid : mindeventsSettings.post_id
				},
				success: function(response) {
					eventsCalendar.attr('style', false);
					eventsCalendar.html(response.html);
					console.log(response);
				},
				error: function (response) {
					console.log('An error occurred.');
					console.log(response);
				},
			});

		})


		$(document).on('click', '.edit-button.update-event', function (event) {
			event.preventDefault();
			var subid = $(this).data('subid');
			var eventsCalendar = $('#eventsCalendar');
			var meta = {};
			$("#editBox .form-section > input, #editBox .form-section > textarea").each(function() {
				meta[$(this).attr("name")] = $(this).val();
			});
			console.log(meta);
			$.ajax({
				url : mindeventsSettings.ajax_url,
				type : 'post',
				data : {
					action : 'mindevents_updatesubevent',
					eventid : subid,
					parentid : mindeventsSettings.post_id,
					meta : meta
				},
				success: function(response) {
					eventsCalendar.html(response.data.html);
					$('#editBox').fadeOut(200, function() {
						$(this).remove();
					});
				},
				error: function (response) {
					console.log('An error occurred.');
					console.log(response);
				},
			});



			console.log(meta);
		});


		$(document).on('click', '.edit-button.cancel', function (event) {
			event.preventDefault();
			$('#editBox').fadeOut(200, function() {
				$(this).remove();
			});
		});



		$(document).on('click', 'td .event span.edit', function (event) {
			event.preventDefault();
			var thisEvent = $(this).parent('.event');
			var eventid = $(this).data('subid');
			var calendarContainer = $('#eventsCalendar')

			$.ajax({
  			url : mindeventsSettings.ajax_url,
  			type : 'post',
  			data : {
  				action : 'mindevents_editevent',
  				eventid : eventid,
					parentid : mindeventsSettings.post_id,
  			},
  			success: function(response) {


					calendarContainer.prepend('<div id="editBox"></div>');
					$('#editBox').html(response.data.html);



					// errorBox.prepend('<span class="error-item-'+ eventid +'">Event deleted</span>').addClass('show');
					// setTimeout(function() {
					// 	$('.error-item-'+ eventid +'').fadeOut(400, function() {
					// 		$(this).remove();
					// 	});
					// }, 3000);
					initTimePicker();
					initDatePicker();
  			},
  			error: function (response) {
  				console.log('An error occurred.');
  				console.log(response);
  			},
  		});


		});


		$(document).on('click', 'td .event span.delete', function (event) {
			event.preventDefault();
			var thisEvent = $(this).parent('.event');
			var eventid = $(this).data('subid');
			var errorBox = $('#errorBox');

			$.ajax({
  			url : mindeventsSettings.ajax_url,
  			type : 'post',
  			data : {
  				action : 'mindevents_deleteevent',
  				eventid : eventid,
  			},
  			success: function(response) {
					thisEvent.fadeOut();
					errorBox.prepend('<span class="error-item-'+ eventid +'">Event deleted</span>').addClass('show');
					setTimeout(function() {
						$('.error-item-'+ eventid +'').fadeOut(400, function() {
							$(this).remove();
						});
					}, 3000);

  			},
  			error: function (response) {
  				console.log('An error occurred.');
  				console.log(response);
  			},
  		});


		});


		$(document).on('click', '.clear-occurances', function (event) {
  		event.preventDefault();


      if(confirm("Wait a tic! This will remove ALL occurances of this event in every month. You cannot undo this. Are you REALY sure?")) {

					var eventsCalendar = $('#eventsCalendar');
					var height = eventsCalendar.height();
					var width = eventsCalendar.width();
					eventsCalendar.height(height).width(width);
					eventsCalendar.html('<div class="la-ball-fall"><div></div><div></div><div></div></div>');

		  		$.ajax({
		  			url : mindeventsSettings.ajax_url,
		  			type : 'post',
		  			data : {
							action : 'mindevents_clearevents',
		  				eventid : mindeventsSettings.post_id,
		  			},
		  			success: function(response) {
							$('#errorBox').removeClass('show').html('');
							eventsCalendar.html(response.html);
							eventsCalendar.attr('style', false);

		  			},
		  			error: function (response) {
		  				console.log('An error occurred.');
		  				console.log(response);
		  			},
		  		});

		  	}
			})



  });

} ( this, jQuery ));
