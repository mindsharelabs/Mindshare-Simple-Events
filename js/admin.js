const MINDEVENTS_PREPEND = 'mindevents_';

(function ($) {
    'use strict';

    const settings = window.mindeventsSettings || {};
    const i18n = settings.i18n || {};

    // Where focus goes back to when the dialog closes.
    let returnFocusTo = null;
    let isDraggingOccurrence = false;
    let suppressDayClick = false;

    function getCurrentCalendarState() {
        const $calendar = $('#mindEventCalendar');

        return {
            month: parseInt($calendar.data('month'), 10) || 0,
            year: parseInt($calendar.data('year'), 10) || 0
        };
    }

    function assignNestedValue(target, name, value) {
        const keys = name.replace(/\]/g, '').split('[');
        let pointer = target;

        keys.forEach(function (key, index) {
            if (!key) {
                return;
            }

            if (index === keys.length - 1) {
                pointer[key] = value;
                return;
            }

            if (!pointer[key] || typeof pointer[key] !== 'object') {
                pointer[key] = {};
            }

            pointer = pointer[key];
        });
    }

    function serializeForm($form) {
        const data = {};

        $.each($form.serializeArray(), function (_, field) {
            assignNestedValue(data, field.name, field.value);
        });

        return data;
    }

    function getModal() {
        let $modal = $('.mindevents-admin-modal');

        if ($modal.length) {
            return $modal;
        }

        $modal = $(`
            <div class="mindevents-admin-modal" aria-hidden="true">
                <div class="mindevents-admin-modal__backdrop"></div>
                <div class="mindevents-admin-modal__dialog" role="dialog" aria-modal="true" tabindex="-1">
                    <div class="mindevents-admin-modal__content"></div>
                </div>
            </div>
        `);

        $('body').append($modal);

        return $modal;
    }

    function initColorPickers($scope) {
        if ($.fn.wpColorPicker) {
            $scope.find('.mindevents-color-field').wpColorPicker();
        }
    }

    /**
     * Show content in the dialog. The first call moves focus into it and
     * remembers `trigger`, so focus can go back there on close.
     */
    function openModal(content, trigger) {
        const $modal = getModal();
        const $dialog = $modal.find('.mindevents-admin-modal__dialog');
        const wasOpen = $modal.hasClass('is-open');

        if (!wasOpen) {
            returnFocusTo = trigger || document.activeElement;
        }

        $modal.find('.mindevents-admin-modal__content').html(content);
        initColorPickers($modal);

        const $heading = $dialog.find('.mindevents-admin-heading').first();
        if ($heading.length) {
            $heading.attr('id', 'mindevents-admin-modal-title');
            $dialog.attr('aria-labelledby', 'mindevents-admin-modal-title').removeAttr('aria-label');
        } else {
            $dialog.removeAttr('aria-labelledby').attr('aria-label', i18n.editOccurrence);
        }

        $modal.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('mindevents-admin-modal-open');

        if (!wasOpen) {
            $dialog.trigger('focus');
        }
    }

    function closeModal() {
        const $modal = $('.mindevents-admin-modal');

        if (!$modal.hasClass('is-open')) {
            return;
        }

        $modal.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('mindevents-admin-modal-open');
        restoreFocus();
    }

    /**
     * Saving re-renders the calendar, replacing the button that opened the
     * dialog. Return focus to the same date's new button, or failing that
     * (it moved to another month) to the calendar's first add button.
     */
    function restoreFocus() {
        let target = returnFocusTo;
        returnFocusTo = null;

        if (target && !document.body.contains(target)) {
            const subid = $(target).data('subid');
            target = $('.mindevents-admin-occurrence__edit').filter(function () {
                return $(this).data('subid') === subid;
            }).get(0) || $('.mindevents-admin-add-date').get(0);
        }

        if (target) {
            target.focus();
        }
    }

    // The media library opens as its own dialog on top of ours.
    function mediaLibraryIsOpen() {
        return $('.media-modal:visible').length > 0;
    }

    // Keep Tab and Shift+Tab cycling inside the open dialog.
    function trapFocus(event) {
        const $dialog = $('.mindevents-admin-modal.is-open .mindevents-admin-modal__dialog');
        if (!$dialog.length || mediaLibraryIsOpen()) {
            return;
        }

        const dialog = $dialog.get(0);
        const focusable = $dialog.find('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible').get();

        if (!focusable.length) {
            event.preventDefault();
            dialog.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = document.activeElement;

        if (active !== dialog && !dialog.contains(active)) {
            event.preventDefault();
            first.focus();
        } else if (event.shiftKey && (active === first || active === dialog)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function setModalLoading(trigger) {
        openModal($('<div class="mindevents-admin-loading" role="status" aria-live="polite">').append($('<span>').text(i18n.loadingEditor)), trigger);
    }

    function showErrors(messages) {
        const $errorBox = $('#errorBox');

        if (!$errorBox.length) {
            return;
        }

        const items = (messages || []).filter(Boolean);
        if (!items.length) {
            $errorBox.removeClass('is-visible').empty();
            return;
        }

        $errorBox.empty().addClass('is-visible');

        items.forEach(function (message) {
            $errorBox.append($('<p>').text(message));
        });
    }

    function setCalendarHtml(html) {
        $('#eventsCalendar').html(html);
        initDragDrop();
    }

    function requestCalendar(direction) {
        const state = getCurrentCalendarState();

        if (!settings.ajax_url || !state.month || !state.year) {
            return;
        }

        $.ajax({
            url: settings.ajax_url,
            type: 'post',
            dataType: 'json',
            data: {
                action: MINDEVENTS_PREPEND + 'movecalendar',
                nonce: settings.nonce,
                direction: direction || 'current',
                month: state.month,
                year: state.year,
                eventid: settings.post_id
            }
        }).done(function (response) {
            if (response && response.html) {
                setCalendarHtml(response.html);
            }
        });
    }

    function initDragDrop() {
        if (!$.fn.draggable || !$.fn.droppable) {
            return;
        }

        const $occurrences = $('.mindevents-admin-occurrence');
        const $days = $('.mindevents-calendar-day').not('.mindevents-calendar-day--empty');

        if ($occurrences.filter('.ui-draggable').length) {
            $occurrences.draggable('destroy');
        }

        if ($days.filter('.ui-droppable').length) {
            $days.droppable('destroy');
        }

        $occurrences.draggable({
            helper: 'clone',
            appendTo: 'body',
            cancel: '.mindevents-admin-occurrence__delete',
            distance: 6,
            revert: 'invalid',
            zIndex: 99999,
            start: function () {
                isDraggingOccurrence = true;
            },
            stop: function () {
                window.setTimeout(function () {
                    isDraggingOccurrence = false;
                }, 0);
            }
        });

        $days.droppable({
            accept: '.mindevents-admin-occurrence',
            tolerance: 'pointer',
            hoverClass: 'is-drop-target',
            drop: function (_, ui) {
                const $target = $(this);
                const $dragged = $(ui.draggable);
                const targetDate = $target.data('date');
                const eventId = $dragged.find('.mindevents-admin-occurrence__edit').data('subid');

                if (!targetDate || !eventId) {
                    return;
                }

                suppressDayClick = true;
                window.setTimeout(function () {
                    suppressDayClick = false;
                }, 150);

                $.ajax({
                    url: settings.ajax_url,
                    type: 'post',
                    dataType: 'json',
                    data: {
                        action: MINDEVENTS_PREPEND + 'moveevent',
                        nonce: settings.nonce,
                        eventid: eventId,
                        new_date: targetDate
                    }
                }).done(function (response) {
                    if (response && response.success) {
                        requestCalendar('current');
                        return;
                    }

                    showErrors([response && response.data ? response.data : i18n.cannotMove]);
                }).fail(function () {
                    showErrors([i18n.cannotMove]);
                });
            }
        });
    }

    $(function () {
        initDragDrop();
        initColorPickers($('#defaultEventMeta'));
    });

    $(document).on('click', '.mindevents-admin-nav', function (event) {
        event.preventDefault();
        requestCalendar($(this).data('dir'));
    });

    $(document).on('click', '#eventsCalendar .mindevents-calendar-day', function (event) {
        if (isDraggingOccurrence || suppressDayClick) {
            return;
        }

        if ($(this).hasClass('mindevents-calendar-day--empty')) {
            return;
        }

        if ($(event.target).closest('.mindevents-admin-occurrence').length) {
            return;
        }

        event.preventDefault();

        const $day = $(this);
        const date = $day.data('date');

        if (!date) {
            return;
        }

        const meta = serializeForm($('#defaultEventMeta'));

        $.ajax({
            url: settings.ajax_url,
            type: 'post',
            dataType: 'json',
            data: {
                action: MINDEVENTS_PREPEND + 'selectday',
                nonce: settings.nonce,
                eventid: settings.post_id,
                date: date,
                meta: meta
            }
        }).done(function (response) {
            if (response && response.html) {
                setCalendarHtml(response.html);
            }

            showErrors(response && response.errors ? response.errors : []);
        }).fail(function () {
            showErrors([i18n.cannotAdd]);
        });
    });

    $(document).on('click', '.mindevents-admin-occurrence__edit', function (event) {
        event.preventDefault();

        const eventId = $(this).data('subid');

        if (!eventId) {
            return;
        }

        setModalLoading(this);

        $.ajax({
            url: settings.ajax_url,
            type: 'post',
            dataType: 'json',
            data: {
                action: MINDEVENTS_PREPEND + 'editevent',
                nonce: settings.nonce,
                eventid: eventId
            }
        }).done(function (response) {
            if (response && response.success && response.data && response.data.html) {
                openModal(response.data.html);
                return;
            }

            closeModal();
            showErrors([i18n.cannotLoad]);
        }).fail(function () {
            closeModal();
            showErrors([i18n.cannotLoad]);
        });
    });

    $(document).on('click', '.update-event', function (event) {
        event.preventDefault();

        const eventId = $(this).data('subid');
        const meta = serializeForm($('#subEventEdit'));

        $.ajax({
            url: settings.ajax_url,
            type: 'post',
            dataType: 'json',
            data: {
                action: MINDEVENTS_PREPEND + 'updatesubevent',
                nonce: settings.nonce,
                eventid: eventId,
                meta: meta
            }
        }).done(function (response) {
            if (response && response.success && response.data && response.data.html) {
                setCalendarHtml(response.data.html);
                closeModal();
                showErrors([]);
                return;
            }

            showErrors([response && response.data ? response.data : i18n.cannotUpdate]);
        }).fail(function () {
            showErrors([i18n.cannotUpdate]);
        });
    });

    $(document).on('click', '.mindevents-admin-occurrence__delete', function (event) {
        event.preventDefault();

        const eventId = $(this).data('subid');
        if (!eventId || !window.confirm(i18n.confirmDelete)) {
            return;
        }

        $.ajax({
            url: settings.ajax_url,
            type: 'post',
            dataType: 'json',
            data: {
                action: MINDEVENTS_PREPEND + 'deleteevent',
                nonce: settings.nonce,
                eventid: eventId
            }
        }).done(function (response) {
            if (response && response.success) {
                requestCalendar('current');
                showErrors([]);
                return;
            }

            showErrors([response && response.data ? response.data : i18n.cannotDelete]);
        }).fail(function () {
            showErrors([i18n.cannotDelete]);
        });
    });

    $(document).on('click', '.clear-occurances', function (event) {
        event.preventDefault();

        if (!window.confirm(i18n.confirmClear)) {
            return;
        }

        $.ajax({
            url: settings.ajax_url,
            type: 'post',
            dataType: 'json',
            data: {
                action: MINDEVENTS_PREPEND + 'clearevents',
                nonce: settings.nonce,
                eventid: settings.post_id
            }
        }).done(function (response) {
            if (response && response.html) {
                setCalendarHtml(response.html);
                showErrors([]);
                return;
            }

            showErrors([i18n.cannotClear]);
        }).fail(function () {
            showErrors([i18n.cannotClear]);
        });
    });

    $(document).on('click', '.mindevents-admin-modal__backdrop, .cancel', function (event) {
        event.preventDefault();
        closeModal();
    });

    // Organizer image: pick from the media library into a hidden ID field.
    $(document).on('click', '.mindevents-image-choose', function (event) {
        event.preventDefault();

        if (!window.wp || !wp.media) {
            return;
        }

        const $field = $(this).closest('.mindevents-image-field');
        const chooser = this;
        const frame = wp.media({
            title: i18n.chooseImage,
            button: { text: i18n.useImage },
            library: { type: 'image' },
            multiple: false
        });

        frame.on('select', function () {
            const image = frame.state().get('selection').first().toJSON();
            const url = (image.sizes && image.sizes.thumbnail) ? image.sizes.thumbnail.url : image.url;

            $field.find('.mindevents-image-id').val(image.id).trigger('change');
            $field.find('.mindevents-image-preview').empty().append($('<img>').attr({ src: url, alt: '' }));
            $field.find('.mindevents-image-remove').prop('hidden', false);
        });

        // Return focus to the button, inside our dialog if there is one.
        frame.on('close', function () {
            chooser.focus();
        });

        frame.open();
    });

    $(document).on('click', '.mindevents-image-remove', function (event) {
        event.preventDefault();

        const $field = $(this).closest('.mindevents-image-field');
        $field.find('.mindevents-image-id').val('').trigger('change');
        $field.find('.mindevents-image-preview').empty();
        $(this).prop('hidden', true);
        $field.find('.mindevents-image-choose').trigger('focus');
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Tab') {
            trapFocus(event);
        } else if (event.key === 'Escape' && !mediaLibraryIsOpen()) {
            closeModal();
        }
    });
})(jQuery);
