const MINDEVENTS_PREPEND = 'mindevents_';

(function ($) {
    'use strict';

    const settings = window.mindeventsSettings || {};

    function syncFilterPanel($form) {
        const isOpen = $form.hasClass('is-open');
        $form.find('.mindevents-filter-toggle').attr('aria-expanded', isOpen ? 'true' : 'false');
        $form.find('.mindevents-filter-panel').prop('hidden', !isOpen);
    }

    function syncMultiSelect($multiselect) {
        const checked = $multiselect.find('input[type="checkbox"]:checked');
        let label = $multiselect.data('default-label') || 'Categories';

        if (checked.length === 1) {
            label = $.trim(checked.first().closest('.mindevents-filter-checkbox').find('.mindevents-filter-checkbox-text').text());
        } else if (checked.length > 1) {
            label = `${checked.length} Categories`;
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
                <div class="mindevents-modal__dialog" role="dialog" aria-modal="true">
                    <div class="mindevents-modal__content"></div>
                </div>
            </div>
        `);

        $('body').append($modal);

        return $modal;
    }

    function openModal(html) {
        const $modal = getModal();
        $modal.find('.mindevents-modal__content').html(html);
        $modal.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('mindevents-has-modal');
    }

    function closeModal() {
        const $modal = $('.mindevents-modal');
        $modal.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('mindevents-has-modal');
    }

    function setModalLoading() {
        openModal('<div class="mindevents-loading" role="status" aria-live="polite"><span>Loading event details...</span></div>');
    }

    function closeDropdownMenus() {
        $('.add-to-calendar-dropdown').removeClass('is-open');
        $('.add-to-calendar-button').attr('aria-expanded', 'false');
    }

    function requestEventMeta(eventId) {
        if (!eventId || !settings.ajax_url) {
            return;
        }

        setModalLoading();

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

            openModal('<div class="mindevents-notice">Unable to load event details right now.</div>');
        }).fail(function () {
            openModal('<div class="mindevents-notice">Unable to load event details right now.</div>');
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
        requestEventMeta($(this).data('eventid'));
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
        if (event.key === 'Escape') {
            closeModal();
            closeDropdownMenus();
            $('.mindevents-multiselect.is-open').removeClass('is-open').each(function () {
                syncMultiSelect($(this));
            });
        }
    });
})(jQuery);
