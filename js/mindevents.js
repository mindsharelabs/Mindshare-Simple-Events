const MINDEVENTS_PREPEND = 'mindevents_';

(function ($) {
    'use strict';

    const settings = window.mindeventsSettings || {};
    const i18n = settings.i18n || {};

    // Where focus goes back to when the dialog closes.
    let returnFocusTo = null;

    function syncFilterPanel($form) {
        const isOpen = $form.hasClass('is-open');
        $form.find('.mindevents-filter-toggle').attr('aria-expanded', isOpen ? 'true' : 'false');
        $form.find('.mindevents-filter-panel').prop('hidden', !isOpen);
    }

    function syncMultiSelect($multiselect) {
        const checked = $multiselect.find('input[type="checkbox"]:checked');
        let label = $multiselect.data('default-label') || i18n.categories;

        if (checked.length === 1) {
            label = $.trim(checked.first().closest('.mindevents-filter-checkbox').find('.mindevents-filter-checkbox-text').text());
        } else if (checked.length > 1) {
            label = i18n.categoriesCount.replace('%d', checked.length);
        }

        $multiselect.find('.mindevents-multiselect-label').text(label);
        $multiselect.find('.mindevents-multiselect-toggle').attr('aria-expanded', $multiselect.hasClass('is-open') ? 'true' : 'false');
    }

    function syncFilterFormState($form) {
        const hasSearch = $.trim($form.find('input[name="event_search"]').val() || '') !== '';
        const hasCategories = $form.find('input[name="event_category_filter[]"]:checked').length > 0;
        $form.toggleClass('has-active-filters', hasSearch || hasCategories);
    }

    function initializeFilters() {
        $('.mindevents-event-filters').each(function () {
            const $form = $(this);
            syncFilterPanel($form);
            syncFilterFormState($form);

            $form.find('.mindevents-multiselect').each(function () {
                syncMultiSelect($(this));
            });
        });
    }

    function getModal() {
        let $modal = $('.mindevents-modal');

        if ($modal.length) {
            return $modal;
        }

        $modal = $(`
            <div class="mindevents-modal" aria-hidden="true">
                <div class="mindevents-modal__backdrop"></div>
                <div class="mindevents-modal__dialog" role="dialog" aria-modal="true" tabindex="-1">
                    <div class="mindevents-modal__content"></div>
                </div>
            </div>
        `);

        $('body').append($modal);

        return $modal;
    }

    /**
     * Show content in the dialog. The first call moves focus into it and
     * remembers `trigger`, so focus can go back there on close.
     */
    function openModal(content, trigger) {
        const $modal = getModal();
        const $dialog = $modal.find('.mindevents-modal__dialog');
        const wasOpen = $modal.hasClass('is-open');

        if (!wasOpen) {
            returnFocusTo = trigger || document.activeElement;
        }

        $modal.find('.mindevents-modal__content').html(content);
        labelDialog($dialog);
        $modal.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('mindevents-has-modal');

        if (!wasOpen) {
            $dialog.trigger('focus');
        }
    }

    // Name the dialog after the event it shows, or generically while loading.
    function labelDialog($dialog) {
        const $title = $dialog.find('.mindevents-event-meta__title, .mindevents-mini-day-panel__title').first();

        if ($title.length) {
            $title.attr('id', 'mindevents-modal-title');
            $dialog.attr('aria-labelledby', 'mindevents-modal-title').removeAttr('aria-label');
        } else {
            $dialog.removeAttr('aria-labelledby').attr('aria-label', i18n.eventDetails);
        }
    }

    function closeModal() {
        const $modal = $('.mindevents-modal');

        if (!$modal.hasClass('is-open')) {
            return;
        }

        $modal.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('mindevents-has-modal');

        if (returnFocusTo && document.body.contains(returnFocusTo)) {
            returnFocusTo.focus();
        }
        returnFocusTo = null;
    }

    // Keep Tab and Shift+Tab cycling inside the open dialog.
    function trapFocus(event) {
        const $dialog = $('.mindevents-modal.is-open .mindevents-modal__dialog');
        if (!$dialog.length) {
            return;
        }

        const dialog = $dialog.get(0);
        const focusable = $dialog.find('a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible').get();

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
        openModal($('<div class="mindevents-loading" role="status" aria-live="polite">').append($('<span>').text(i18n.loadingDetails)), trigger);
    }

    function closeDropdownMenus() {
        $('.add-to-calendar-dropdown').removeClass('is-open');
        $('.add-to-calendar-button').attr('aria-expanded', 'false');
    }

    function requestEventMeta(eventId, trigger) {
        if (!eventId || !settings.ajax_url) {
            return;
        }

        setModalLoading(trigger);

        $.ajax({
            url: settings.ajax_url,
            type: 'post',
            dataType: 'json',
            data: {
                action: MINDEVENTS_PREPEND + 'get_event_meta_html',
                eventid: eventId
            }
        }).done(function (response) {
            if (response && response.success && response.data && response.data.html) {
                openModal(response.data.html);
                return;
            }

            openModal($('<div class="mindevents-notice">').text(i18n.cannotLoad));
        }).fail(function () {
            openModal($('<div class="mindevents-notice">').text(i18n.cannotLoad));
        });
    }

    $(function () {
        initializeFilters();
    });

    $(document).on('click', '.mindevents-filter-toggle', function (event) {
        event.preventDefault();

        const $form = $(this).closest('.mindevents-event-filters');
        $form.toggleClass('is-open');
        syncFilterPanel($form);
    });

    $(document).on('click', '.mindevents-multiselect-toggle', function (event) {
        event.preventDefault();
        event.stopPropagation();

        const $multiselect = $(this).closest('.mindevents-multiselect');

        $('.mindevents-multiselect').not($multiselect).removeClass('is-open').each(function () {
            syncMultiSelect($(this));
        });

        $multiselect.toggleClass('is-open');
        syncMultiSelect($multiselect);
    });

    $(document).on('input change', '.mindevents-event-filters input', function () {
        const $form = $(this).closest('.mindevents-event-filters');
        const $multiselect = $(this).closest('.mindevents-multiselect');

        if ($multiselect.length) {
            syncMultiSelect($multiselect);
        }

        syncFilterFormState($form);
    });

    $(document).on('click', '.add-to-calendar-button', function (event) {
        event.preventDefault();
        event.stopPropagation();

        const $dropdown = $(this).closest('.add-to-calendar-dropdown');
        const willOpen = !$dropdown.hasClass('is-open');

        closeDropdownMenus();
        $dropdown.toggleClass('is-open', willOpen);
        $(this).attr('aria-expanded', willOpen ? 'true' : 'false');
    });

    $(document).on('click', '.mindevents-calendar-event-toggle', function (event) {
        event.preventDefault();
        requestEventMeta($(this).data('eventid'), this);
    });

    // A mini calendar day: its list is already in the page, in a <template>.
    $(document).on('click', '.mindevents-mini-day[data-template]', function (event) {
        event.preventDefault();

        const template = document.getElementById($(this).attr('data-template'));
        if (template) {
            openModal(template.content.cloneNode(true), this);
        }
    });

    $(document).on('click', '.mindevents-modal__backdrop, .event-meta-close', function (event) {
        event.preventDefault();
        closeModal();
    });

    $(document).on('click', function (event) {
        if (!$(event.target).closest('.mindevents-multiselect').length) {
            $('.mindevents-multiselect.is-open').removeClass('is-open').each(function () {
                syncMultiSelect($(this));
            });
        }

        if (!$(event.target).closest('.add-to-calendar-dropdown').length) {
            closeDropdownMenus();
        }
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Tab') {
            trapFocus(event);
            return;
        }

        if (event.key !== 'Escape') {
            return;
        }

        // Close the innermost open thing, returning focus to what opened it.
        const $dropdown = $('.add-to-calendar-dropdown.is-open').first();
        if ($dropdown.length) {
            closeDropdownMenus();
            $dropdown.find('.add-to-calendar-button').trigger('focus');
            return;
        }

        const $multiselect = $('.mindevents-multiselect.is-open').first();
        if ($multiselect.length) {
            $multiselect.removeClass('is-open');
            syncMultiSelect($multiselect);
            $multiselect.find('.mindevents-multiselect-toggle').trigger('focus');
            return;
        }

        closeModal();
    });
})(jQuery);
