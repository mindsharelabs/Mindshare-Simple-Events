# Mindshare Simple Events Plugin

A comprehensive WordPress events management plugin that provides calendar functionality, event scheduling, WooCommerce integration for ticketing, and mobile-responsive design.

## Features

### Core Event Management

- **Custom Post Types**: Events and Sub-events for complex event structures
- **Event Categories**: Organize events with hierarchical categories
- **Event Types**: Support for single events and recurring event series
- **Calendar Display**: Both calendar and list view options
- **Mobile Responsive**: Optimized for mobile devices with touch-friendly interface

### Calendar Functionality

- **Interactive Calendar**: Full calendar view with event highlighting
- **Event Colors**: Color-coded events based on categories or custom colors
- **Navigation**: Previous/next month navigation
- **Date Filtering**: Show past events or upcoming events only
- **Event Details**: Popup event details with rich information

### WooCommerce Integration

- **Ticket Sales**: Direct integration with WooCommerce for event ticket sales
- **Product Linking**: Link events to WooCommerce products
- **Cart Integration**: Add to cart functionality directly from calendar
- **Stock Management**: Display available tickets and sold-out status
- **Pricing Display**: Show ticket prices and purchase options

### Schema.org Support

- **SEO Optimization**: Automatic schema markup for better search engine visibility
- **Event Structured Data**: Rich snippets for event information
- **Location Data**: Integrated location information for events

### Advanced Features

- **AJAX Integration**: Smooth user experience with AJAX-powered interactions
- **Template System**: Override templates in your theme
- **Hook System**: Extensive action and filter hooks for customization
- **Admin Interface**: User-friendly admin interface for event management
- **Multi-date Events**: Support for events spanning multiple dates

## Installation

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- WooCommerce 5.0+ (optional, for ticketing features)

### Plugin Installation

1. Download the plugin files
2. Upload to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure settings under Events > Settings

### Manual Installation

```bash
# Clone the repository
git clone https://github.com/thejameswilliam/Make-Events-Plugin.git

# Copy to WordPress plugins directory
cp -r Make-Events-Plugin /path/to/wordpress/wp-content/plugins/mindshare-events

# Activate via WordPress admin or WP-CLI
wp plugin activate mindshare-events
```

## Usage

### Creating Events

1. **Add New Event**

   - Navigate to Events > Add New in your WordPress admin
   - Enter event title, description, and featured image
   - Set event dates, times, and recurrence settings
   - Choose event categories and colors
   - Configure ticketing options (if WooCommerce is active)

2. **Event Types**

   - **Single Event**: One-time event with specific date/time
   - **Recurring Event**: Series of events with multiple dates
   - **Multi-day Event**: Events spanning multiple consecutive days

3. **Display Options**
   - **Calendar View**: Interactive calendar with event highlighting
   - **List View**: Linear list of events with detailed information
   - **Mobile View**: Automatically optimized for mobile devices

### Shortcodes

```php
// Display event calendar
[mindevents_calendar]

// Display event list
[mindevents_list]

// Display specific event
[mindevents_event id="123"]
```

### Template Overrides

Create custom templates in your theme:

- `single-events.php` - Single event page
- `archive-events.php` - Events archive page
- `taxonomy-event-category.php` - Event category pages

### Custom Hooks

```php
// Before event content
add_action('mindevents_before_main_content', 'my_custom_function');

// After event content
add_action('mindevents_after_main_content', 'my_custom_function');

// Customize event display
add_filter('mindevents_event_image', 'my_custom_event_image');
```

## Configuration

### Plugin Settings

Access plugin settings via **Events > Settings** in your WordPress admin:

- **Calendar Start Day**: Set whether week starts on Sunday or Monday
- **Currency Symbol**: Configure currency for ticket pricing
- **Show Past Events**: Toggle visibility of past events
- **WooCommerce Integration**: Enable/disable e-commerce features
- **Color Schemes**: Set default event colors

### WooCommerce Setup

1. Install and activate WooCommerce
2. Enable WooCommerce integration in plugin settings
3. Create products for your events
4. Link events to products in the event editor

### Event Categories

- Create event categories with custom colors
- Assign categories to events for organization
- Use category colors for visual distinction in calendar

## File Structure

```
mindshare-events/
├── mindshare-events.php          # Main plugin file
├── README.md                     # This file
├── package.json                  # Node.js dependencies
├── gulpfile.js                   # Build automation
├── css/
│   └── style.css                 # Main stylesheet
├── js/
│   ├── admin.js                  # Admin interface JavaScript
│   └── mindevents.js             # Frontend JavaScript
├── inc/
│   ├── admin.class.php           # Admin functionality
│   ├── ajax.class.php            # AJAX handlers
│   ├── events.class.php          # Core event calendar class
│   ├── front-end.php             # Frontend hooks and filters
│   ├── posttypes.php             # Custom post types and taxonomies
│   ├── woocommerce.php           # WooCommerce integration
│   └── utilities.php             # Helper functions
├── templates/
│   ├── single-events.php         # Single event template
│   ├── archive-events.php        # Events archive template
│   └── taxonomy-event-category.php # Event category template
└── sass/
    ├── style.scss                # Main SCSS file
    ├── calendar.scss             # Calendar-specific styles
    ├── forms-main.scss           # Form styling
    └── helpers/                  # SCSS helper files
```

## Development

### Build Process

```bash
# Install dependencies
npm install

# Build CSS from SASS
gulp sass

# Watch for changes
gulp watch
```

### Core Classes

- **`mindEvents`** - Main plugin class
- **`mindEventCalendar`** - Calendar rendering and event management
- **`mindEventsCPTS`** - Custom post types and taxonomies
- **`mindEventsAdmin`** - Admin interface management
- **`mindEventsAjax`** - AJAX request handling

### Database Schema

The plugin creates the following custom post types:

- `events` - Main event posts
- `sub_event` - Individual event occurrences
- `event_category` - Event categorization taxonomy

### Hooks and Filters

```php
// Action Hooks
do_action('mindevents_before_add_sub_event', $event_id, $meta);
do_action('mindevents_after_add_sub_event', $event_id, $sub_event_id, $meta);
do_action('mindevents_before_delete_sub_event', $sub_event_id);

// Filter Hooks
apply_filters('mindevents_event_image', $image, $event_id);
apply_filters('mindevents_calendar_label', $label);
apply_filters('mindevents_list_label', $label);
```

## API Integration

### REST API Endpoints

The plugin supports WordPress REST API:

- `GET /wp-json/wp/v2/events` - Get events
- `GET /wp-json/wp/v2/event_category` - Get event categories

### Custom Endpoints

```php
// Get events for specific date range
GET /wp-json/mindevents/v1/events?start_date=2024-01-01&end_date=2024-01-31

// Get calendar data
GET /wp-json/mindevents/v1/calendar?month=1&year=2024
```

## Troubleshooting

### Common Issues

1. **Calendar not displaying**

   - Check that JavaScript is enabled
   - Verify no JavaScript errors in browser console
   - Ensure proper theme integration

2. **WooCommerce integration issues**

   - Confirm WooCommerce is active
   - Check plugin settings for WooCommerce integration
   - Verify product linking in event settings

3. **CSS styling issues**
   - Check for theme conflicts
   - Verify CSS files are loading properly
   - Consider template overrides

### Debug Mode

Enable WordPress debug mode to troubleshoot issues:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Coding Standards

- Follow WordPress coding standards
- Use proper PHP DocBlocks
- Include unit tests for new features
- Maintain backward compatibility

## License

This project is licensed under the GPL-3.0 License - see the [LICENSE](LICENSE) file for details.

## Support

For support and questions:

- Create an issue on GitHub
- Visit the plugin support forum
- Contact [Mindshare Labs](https://mind.sh/are)

## Credits

- **Author**: Mindshare Labs, Inc.
- **Plugin URI**: https://mind.sh/are
- **Version**: 1.3.4
- **Requires**: WordPress 5.0+, PHP 7.4+

## Changelog

### Version 1.3.4

- Latest stable release
- Enhanced WooCommerce integration
- Improved mobile responsiveness
- Bug fixes and performance improvements

### Previous Versions

- See [CHANGELOG.md](CHANGELOG.md) for detailed version history

---

**Note**: This plugin is actively maintained and developed. Please report any issues or feature requests through the GitHub repository.
