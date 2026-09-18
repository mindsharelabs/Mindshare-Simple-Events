const MINDEVENTS_PREPEND = 'mindevents_';

(function ($) {
    'use strict';

    const settings = window.mindeventsSettings || {};
    const i18n = settings.i18n || {};
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
                <div class="mindevents-admin-modal__dialog" role="dialog" aria-modal="true">
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

    function openModal(html) {
        const $modal = getModal();
        $modal.find('.mindevents-admin-modal__content').html(html);
        initColorPickers($modal);
        $modal.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('mindevents-admin-modal-open');
    }

    function closeModal() {
        const $modal = $('.mindevents-admin-modal');
        $modal.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('mindevents-admin-modal-open');
    }

    function setModalLoading() {
        openModal($('<div class="mindevents-admin-loading" role="status" aria-live="polite">').append($('<span>').text(i18n.loadingEditor)));
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

        setModalLoading();

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

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
})(jQuery);
