/**
 * Editor side of the Events Calendar block: its settings and a preview.
 * The block is drawn on the server (inc/core/block.php), so nothing is
 * saved here but the settings.
 *
 * Plain JavaScript against the editor's globals, so there is no build step.
 */
(function (wp) {
    'use strict';

    const el = wp.element.createElement;
    const __ = wp.i18n.__;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { CheckboxControl, Disabled, PanelBody, SelectControl, Spinner } = wp.components;
    const { useSelect } = wp.data;
    const { decodeEntities } = wp.htmlEntities;
    const ServerSideRender = wp.serverSideRender;

    function DisplaySettings({ attributes, setAttributes, events }) {
        const eventOptions = [{ value: '0', label: __('All events', 'simple-events') }].concat(
            (events || []).map(function (event) {
                return { value: String(event.id), label: decodeEntities(event.title.rendered) };
            })
        );

        return el(PanelBody, { title: __('Display', 'simple-events') },
            el(SelectControl, {
                label: __('Display type', 'simple-events'),
                value: attributes.display,
                options: [
                    { value: 'calendar', label: __('Calendar', 'simple-events') },
                    { value: 'list', label: __('List', 'simple-events') },
                    { value: 'mini', label: __('Mini calendar', 'simple-events') }
                ],
                onChange: function (display) {
                    setAttributes({ display: display });
                }
            }),
            events ? el(SelectControl, {
                label: __('Event', 'simple-events'),
                help: __('Show every event, or one event and its dates.', 'simple-events'),
                value: String(attributes.event),
                options: eventOptions,
                onChange: function (event) {
                    setAttributes({ event: parseInt(event, 10) || 0 });
                }
            }) : el(Spinner)
        );
    }

    function CategorySettings({ attributes, setAttributes, categories }) {
        function toggle(id, checked) {
            const selected = attributes.categories.filter(function (current) {
                return current !== id;
            });

            setAttributes({ categories: checked ? selected.concat(id) : selected });
        }

        return el(PanelBody, { title: __('Categories', 'simple-events') },
            el('p', { className: 'components-base-control__help' }, __('Show only events in these categories. With none ticked, every event is shown.', 'simple-events')),
            categories ? categories.map(function (category) {
                return el(CheckboxControl, {
                    key: category.id,
                    label: decodeEntities(category.name),
                    checked: attributes.categories.indexOf(category.id) !== -1,
                    onChange: function (checked) {
                        toggle(category.id, checked);
                    }
                });
            }) : el(Spinner)
        );
    }

    wp.blocks.registerBlockType('simple-events/calendar', {
        edit: function (props) {
            const blockProps = useBlockProps();

            const records = useSelect(function (select) {
                const core = select('core');

                return {
                    events: core.getEntityRecords('postType', 'mind_events', { per_page: -1, status: 'publish', orderby: 'title', order: 'asc', _fields: 'id,title' }),
                    categories: core.getEntityRecords('taxonomy', 'mind_event_category', { per_page: -1, _fields: 'id,name' })
                };
            }, []);

            return el('div', blockProps,
                el(InspectorControls, null,
                    el(DisplaySettings, Object.assign({ events: records.events }, props)),
                    // Categories only narrow "All events"; one event is already one event.
                    props.attributes.event ? null : el(CategorySettings, Object.assign({ categories: records.categories }, props))
                ),
                // Disabled: the preview's links and buttons are not for clicking here.
                el(Disabled, null,
                    el(ServerSideRender, { block: 'simple-events/calendar', attributes: props.attributes })
                )
            );
        },

        save: function () {
            return null;
        }
    });
})(window.wp);
