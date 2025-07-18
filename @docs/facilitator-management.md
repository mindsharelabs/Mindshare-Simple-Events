# Facilitator Management

The Mindshare Simple Events plugin includes comprehensive facilitator management functionality that allows administrators to assign and display facilitator information for events and their specific occurrences.

## Overview

The facilitator system operates on two levels:

1. **Event Level**: Overall facilitators for the entire event series
2. **Occurrence Level**: Specific facilitators for individual event dates

## Implementation Details

### Event Level Facilitators

Event level facilitators are managed through an Advanced Custom Fields (ACF) field:

- **Field Name**: `instructors`
- **Field Type**: User selection field (ACF)
- **Location**: Main event post (`events` post type)
- **Purpose**: Assigns overall facilitators to an event series

### Occurrence Level Facilitators

Occurrence level facilitators are managed through WordPress meta fields:

- **Meta Key**: `instructorEmail`
- **Field Type**: Email address (string)
- **Location**: Sub-event posts (`sub_event` post type)
- **Purpose**: Assigns specific facilitators to individual event occurrences

### Priority System

The facilitator display follows a priority hierarchy:

1. **First Priority**: Occurrence-specific facilitator (`instructorEmail` on sub-event)
2. **Second Priority**: Event-level facilitators (`instructors` ACF field on main event)
3. **Fallback**: "Not assigned" message if no facilitators are set

## Admin Interface Integration

### Event Attendees Meta Box

The facilitator information is prominently displayed in the Event Attendees meta box:

- **Location**: Top right corner of each occurrence section
- **Display Format**: "Facilitator: [Name(s)]"
- **Functionality**: Names are clickable links to the user edit page in WordPress admin
- **Multiple Facilitators**: When multiple event-level facilitators exist, they are displayed as comma-separated links

### Visual Design

The facilitator display includes custom CSS styling:

- **Layout**: Flexbox layout with occurrence date on the left, facilitator info on the right
- **Typography**: Smaller font size (0.9em) with bold label
- **Colors**:
  - Label: Dark gray (#333)
  - Links: WordPress admin blue (#0073aa)
  - Hover: Darker blue (#005a87)
  - Not assigned: Light gray (#999) with italic styling
- **Responsive**: Stacks vertically on mobile devices (768px and below)

## Usage Examples

### Setting Event Level Facilitators

1. Edit the main event post
2. Locate the "Instructors" ACF field
3. Select one or more WordPress users
4. Save the event

### Setting Occurrence Level Facilitators

1. Navigate to the event edit page
2. In the Calendar meta box, click on a specific date occurrence
3. Set the `instructorEmail` field to the facilitator's email address
4. Save the occurrence

### Viewing Facilitator Information

1. Edit any event with attendees
2. Scroll to the "Event Attendees" meta box
3. View facilitator information in the top right of each occurrence section

## Technical Implementation

### PHP Code Structure

The facilitator display logic is implemented in the `display_attendees_metabox()` method of the `mindeventsAdmin` class:

```php
// Check for occurrence-specific facilitator
$facilitator_email = get_post_meta($sub_event, 'instructorEmail', true);

// Fallback to event-level facilitators
$parent_facilitators = get_field('instructors', $post->ID);

// Display logic with priority system
if($facilitator_email) {
    // Display occurrence-specific facilitator
} elseif($parent_facilitators && is_array($parent_facilitators)) {
    // Display event-level facilitators
} else {
    // Display "Not assigned" message
}
```

### CSS Classes

The following CSS classes are used for styling:

- `.occurance-header`: Container for the occurrence header with flexbox layout
- `.facilitator-info`: Container for facilitator information
- `.facilitator-label`: Bold label text
- `.facilitator-name`: Styled links for facilitator names
- `.no-facilitator`: Italic styling for "not assigned" text

## Integration with Existing Features

### Events Admin Overview

The facilitator system integrates with the existing "Upcoming Events" admin page, which already displays instructor information in a tabular format.

### Frontend Display

The facilitator information can be displayed on the frontend through the existing instructor display logic in the `get_list_item_html()` method of the `mindEventCalendar` class.

## Future Enhancements

Potential improvements to the facilitator system could include:

1. **Bulk Assignment**: Tools for assigning facilitators to multiple occurrences at once
2. **Facilitator Profiles**: Enhanced facilitator information display with photos and bios
3. **Email Notifications**: Automatic notifications to facilitators about their assigned events
4. **Availability Management**: Integration with facilitator availability calendars
5. **Reporting**: Analytics on facilitator assignments and event coverage

## Troubleshooting

### Common Issues

1. **Facilitator Not Displaying**: Ensure the user exists in WordPress and the email address is correct
2. **ACF Field Missing**: Verify that Advanced Custom Fields plugin is installed and the `instructors` field is properly configured
3. **Styling Issues**: Check that the custom CSS has been properly loaded and not overridden by theme styles

### Debug Information

To debug facilitator display issues:

1. Check the `instructorEmail` meta value on sub-events
2. Verify the `instructors` ACF field value on main events
3. Ensure user accounts exist for the specified email addresses
4. Confirm proper user permissions for accessing user edit links
