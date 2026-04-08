const MINDEVENTS_PREPEND = 'mindevents_';


(function( root, $, undefined ) {
	"use strict";

	$(function () {

		function syncFilterPanel($form) {
			var isOpen = $form.hasClass('is-open');
			$form.find('.mindevents-filter-toggle').attr('aria-expanded', isOpen ? 'true' : 'false');
			$form.find('.mindevents-filter-panel').prop('hidden', !isOpen);
		}

		function syncMultiSelect($multiselect) {
			var checked = $multiselect.find('input[type="checkbox"]:checked');
			var label = $multiselect.data('default-label') || 'Categories';

			if (checked.length === 1) {
				label = $.trim(checked.first().closest('.mindevents-filter-checkbox').find('.mindevents-filter-checkbox-text').text());
			} else if (checked.length > 1) {
				label = checked.length + ' Categories';
			}

			$multiselect.find('.mindevents-multiselect-label').text(label);
			$multiselect.find('.mindevents-multiselect-toggle').attr('aria-expanded', $multiselect.hasClass('is-open') ? 'true' : 'false');
		}

		function syncFilterFormState($form) {
			var hasActiveFilters = false;
			$form.find('input[name="event_start"], input[name="event_end"], input[name="event_search"]').each(function() {
				if ($.trim($(this).val()) !== '') {
					hasActiveFilters = true;
				}
			});

			if ($form.find('input[name="event_category_filter[]"]:checked').length) {
				hasActiveFilters = true;
			}

			$form.toggleClass('has-active-filters', hasActiveFilters);
		}

		$('.mindevents-event-filters').each(function() {
			var $form = $(this);
			syncFilterPanel($form);
			syncFilterFormState($form);
			$form.find('.mindevents-multiselect').each(function() {
				syncMultiSelect($(this));
			});
		});


		$(document).on('click', '.add-to-calendar-button', function (e) {
			e.preventDefault();
			var button = $(this);
			var menu = button.next('.add-to-calendar-menu');

			menu.toggleClass('show');
		});

		//when not clicking on the button or menu, close the menu
		$(document).on('click', function (e) {
			if (!$(e.target).closest('.add-to-calendar-button').length && !$(e.target).closest('.add-to-calendar-menu').length) {
				$('.add-to-calendar-menu').removeClass('show');
			}

			if (!$(e.target).closest('.mindevents-multiselect').length) {
				$('.mindevents-multiselect.is-open').removeClass('is-open').each(function() {
					syncMultiSelect($(this));
				});
			}
		});

		$(document).on('click', '.mindevents-filter-toggle', function (e) {
			e.preventDefault();
			var $form = $(this).closest('.mindevents-event-filters');
			$form.toggleClass('is-open');
			syncFilterPanel($form);
		});

		$(document).on('click', '.mindevents-multiselect-toggle', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $multiselect = $(this).closest('.mindevents-multiselect');
			$('.mindevents-multiselect.is-open').not($multiselect).removeClass('is-open').each(function() {
				syncMultiSelect($(this));
			});
			$multiselect.toggleClass('is-open');
			syncMultiSelect($multiselect);
		});

		$(document).on('input change', '.mindevents-event-filters input', function () {
			var $form = $(this).closest('.mindevents-event-filters');
			var $multiselect = $(this).closest('.mindevents-multiselect');
			if ($multiselect.length) {
				syncMultiSelect($multiselect);
			}
			syncFilterFormState($form);
		});



		function closeAllEventMeta() {
			$(".meta-container").each(function() {
				$(this).removeClass('show').html('');
			});
		}


		$(document).on('click', '.event-meta-close', function (event) {
			closeAllEventMeta();
		});

		//on resize close all event meta
		// $(window).on('resize', function () {
		// 	closeAllEventMeta();
		// });


		$(document).on('click', '.sub-event-toggle', function (event) {
			
			var eventid = $(this).data('eventid');
			
			//if on mobile:
			if ($(window).width() < 768) {
				var metaContainer = $(this).closest('.day-container').after('<div class="meta-container row"></div>');
			} else {
				
				var metaContainer = $(this).closest('.week-row').after('<div class="meta-container row"></div>');
			}

			metaContainer = metaContainer.next('.meta-container').html('<div class="la-ball-fall"><div></div><div></div><div></div></div>');
			

			$.ajax({
				url : mindeventsSettings.ajax_url,
				type : 'post',
				data : {
					action : MINDEVENTS_PREPEND + 'get_event_meta_html',
					eventid : eventid
				},
				success: function(response) {
					closeAllEventMeta();
					metaContainer.html(response.data.html);
					//after the responce is loeaded, scroll into view
					scrollIntoView(metaContainer);
					
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
					$(this).find('.go-to-cart').remove();
					button.prop('disabled', true);
				},
				afterSend: function() {
					
				},
				success: function(response) {
					
					if(response.success) {
						button.prop('disabled', false);
						// Google Analytics 4 add_to_cart event
						
						if (typeof gtag === 'function') {
							
							gtag('event', 'add_to_cart', {
								event_category: 'MindEvents',
								event_label: event_date,
								value: quantity,
								items: [{
									item_id: product_id,
									event_date: event_date, // optional: product name
									quantity: quantity
								}]
							});
						}

						//hide button
						button.hide();
						buttonParent.append('<a class="btn btn-primary go-to-cart w-100 vf-bold" href="' + mindeventsSettings.cart_url + '">Success! Go to Cart.</a>');
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
    if (target.length){
        var top = target.offset().top + -300;
        $('html,body').animate({scrollTop: top}, 1000);
        return false;
    }
  }

} ( this, jQuery ));
