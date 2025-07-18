# Past Events Admin Page

## Overview

The Past Events admin page provides a comprehensive view of all completed events with advanced filtering, sorting, and visual indicators for event performance.

## Features

### Event Display

- **Event Information**: Shows event title, date, sold tickets, checked-in attendees, and instructor
- **Visual Indicators**:
  - Green attendee names for checked-in attendees
  - Red attendee names for non-checked-in attendees
  - Light beige row highlighting for events with no attendees
- **Attendance Rate**: Displays percentage of attendees who checked in
- **Action Buttons**: Quick access to edit event and view event pages

### Filtering Options

- **Date Range**: Filter events by start and end date
- **Event Title Search**: Search events by title/name using WordPress search functionality
- **Attendee Name Search**: Filter events containing specific attendee names with intelligent pagination
- **Instructor Filter**: Filter events by assigned instructor with intelligent pagination
- **Clear Filters**: Reset all filters to default state

### Advanced Filtering Behavior

- **Intelligent Pagination**: When using attendee name or instructor filters, the system:
  - Retrieves all matching events first
  - Applies filtering logic to find relevant events
  - Recalculates pagination based on filtered results
  - Ensures filtered results start from page 1
- **Cross-Page Search**: Attendee and instructor filtering works across all pages, not just the current page
- **Accurate Result Counts**: Total event count reflects actual filtered results

### Sorting

- **Date Sorting**: Sort events by date (ascending/descending)
- **Title Sorting**: Sort events alphabetically by title
- **Visual Indicators**: Sort direction arrows in column headers

### Pagination

- **30 Events Per Page**: Standard pagination with navigation controls
- **Page Navigation**: Previous/Next links and page numbers
- **Dynamic Total Count**: Display total number of events found after filtering
- **Smart Pagination**: Automatically recalculates pages when filters are applied

## Technical Implementation

### Color Coding System

```css
.past-events-table .no-attendees {
  background-color: #fdf7e2; /* Light beige for no attendees */
}
```

### Attendee Name Display

- Green text: `color: green` for checked-in attendees
- Red text: `color: red` for non-checked-in attendees

### Filter Persistence

All filter parameters are maintained in the URL and persist across page navigation and sorting operations.

### Advanced Filtering Logic

- **Event Title Search**: Uses WordPress native search (`s` parameter) for efficient database queries
- **Attendee/Instructor Filtering**: Uses custom logic that:
  - Retrieves all events matching basic criteria
  - Applies attendee/instructor filtering in PHP
  - Manually handles pagination for filtered results
  - Ensures accurate result counts and page calculations

## Usage

1. Navigate to Events > Past Events in WordPress admin
2. Use filter controls to narrow down events
3. Click column headers to sort results
4. Use pagination controls to navigate through results
5. Click "Clear Filters" to reset all filters

## Performance Notes

- Event title search is optimized using WordPress database queries
- Attendee and instructor filtering may be slower for large datasets as it processes all events
- Results are cached per page load for optimal performance
