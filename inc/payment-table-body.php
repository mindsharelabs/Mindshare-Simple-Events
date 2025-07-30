<?php
// This file contains the updated table body for the past events page with payment functionality

if ($events_query->have_posts()) :
    while ($events_query->have_posts()) :
        $events_query->the_post();
        $sub_event_id = get_the_id();
        $parent_id = wp_get_post_parent_id($sub_event_id);

        $event_type = get_post_meta($parent_id, 'event_type', true);
        $all_attendees = get_post_meta($parent_id, 'attendees', true);
        
        // Get attendees for this specific event based on event type
        if ($event_type == 'single-event') :
            $attendees = (isset($all_attendees[$parent_id]) && is_array($all_attendees[$parent_id])) ? $all_attendees[$parent_id] : array();
        else :
            $attendees = (isset($all_attendees[$sub_event_id]) && is_array($all_attendees[$sub_event_id])) ? $all_attendees[$sub_event_id] : array();
        endif;

        $date = get_post_meta($sub_event_id, 'event_date', true);
        $sold_tickets = is_array($attendees) ? count($attendees) : 0;
        
        // Count checked in attendees
        $checked_in_count = 0;
        if (is_array($attendees)) {
            foreach ($attendees as $attendee) {
                if (isset($attendee['checked_in']) && $attendee['checked_in']) {
                    $checked_in_count++;
                }
            }
        }

        // Get payment information
        $payment_status = get_post_meta($sub_event_id, 'instructor_payment_status', true);
        $payment_amount = get_post_meta($sub_event_id, 'instructor_payment_amount', true);
        $payment_date = get_post_meta($sub_event_id, 'instructor_payment_date', true);
        $payment_notes = get_post_meta($sub_event_id, 'instructor_payment_notes', true);

        // Calculate default payment amount if not set
        if (!$payment_amount) {
            $analytics_settings = get_option('mindevents_analytics_settings', array(
                'badge_rate' => 160,
                'workshop_rate' => 140
            ));
            
            $event_categories = wp_get_post_terms($parent_id, 'event_category', array('fields' => 'names'));
            $is_badge = false;
            if (!empty($event_categories)) {
                foreach ($event_categories as $category) {
                    if (stripos($category, 'badge') !== false) {
                        $is_badge = true;
                        break;
                    }
                }
            }
            $payment_amount = $is_badge ? $analytics_settings['badge_rate'] : $analytics_settings['workshop_rate'];
        }

        // Apply payment status filter from GET parameter
        $payment_status_filter = isset($_GET['payment_status']) ? sanitize_text_field($_GET['payment_status']) : '';
        if (!empty($payment_status_filter)) {
            if ($payment_status_filter === 'paid' && $payment_status !== 'paid') {
                continue;
            }
            if ($payment_status_filter === 'unpaid' && !empty($payment_status)) {
                continue;
            }
            if ($payment_status_filter === 'no_payment' && $payment_status !== 'no_payment') {
                continue;
            }
            if ($payment_status_filter === 'staff_taught' && $payment_status !== 'staff_taught') {
                continue;
            }
        }

        // Calculate attendance rate for styling
        $attendance_rate = $sold_tickets > 0 ? ($checked_in_count / $sold_tickets) * 100 : 0;
        $attendance_class = '';
        if ($sold_tickets == 0) { // No attendees
            $attendance_class = 'no-attendees';
        } elseif ($attendance_rate >= 80) { // High attendance
            $attendance_class = 'high-attendance';
        } elseif ($attendance_rate >= 50) { // Medium attendance
            $attendance_class = 'medium-attendance';
        } elseif ($sold_tickets > 0) { // Low attendance (and sold tickets > 0)
            $attendance_class = 'low-attendance';
        }

        echo '<tr class="' . $attendance_class . '">';
        echo '<td class="check-column"><input type="checkbox" name="event_ids[]" value="' . $sub_event_id . '" class="event-checkbox"></td>';
        echo '<td class="event-date" style="font-weight: 500;">' . esc_html(date('M j, Y', strtotime($date))) . '</td>';
        echo '<td class="event-title">';
        echo '<strong><a href="' . get_edit_post_link($parent_id) . '" target="_blank" style="text-decoration: none;">' . esc_html(get_the_title($parent_id)) . '</a></strong>';
        echo '</td>';
        echo '<td class="event-actions">';
        echo '<div class="button-group" style="display: flex; gap: 5px;">';
        echo '<a href="' . get_edit_post_link($parent_id) . '" target="_blank" class="button button-small button-secondary">Edit</a>';
        echo '<a href="' . get_permalink($parent_id) . '" target="_blank" class="button button-small button-secondary">View</a>';
        echo '</div>';
        echo '</td>';
        echo '<td class="sold-tickets" style="text-align: center; font-weight: 500;">' . esc_html($sold_tickets) . '</td>';
        echo '<td class="checked-in-count" style="text-align: center; font-weight: 500;">';
        if ($sold_tickets > 0) {
            echo esc_html($checked_in_count) . ' <span style="color: #666; font-size: 0.9em;">(' . round($attendance_rate) . '%)</span>';
        } else {
            echo esc_html($checked_in_count);
        }
        echo '</td>';
        echo '<td class="attendee-names" style="font-size: 0.9em;">';
        if ($sold_tickets > 0) :
            $attendee_display_parts = array();
            foreach ($attendees as $attendee) :
                $order = wc_get_order($attendee['order_id']);
                if(!$order) continue;
                $user = get_userdata($order->get_customer_id());
                if ($user) {
                    $name = esc_html($user->display_name);
                    $color = isset($attendee['checked_in']) && $attendee['checked_in'] ? 'green' : 'red';
                    $attendee_display_parts[] = '<span style="color: ' . $color . ';">' . $name . '</span>';
                }
            endforeach;
            echo implode(', ', $attendee_display_parts);
        else :
            echo 'No attendees';
        endif;
        echo '</td>';
        echo '<td class="event-instructor">';
        $instructor = get_post_meta($sub_event_id, 'instructorEmail', true);
        $event_parent_id = wp_get_post_parent_id($sub_event_id);
        $parent_ininstructors = get_field('instructors', $event_parent_id);
        if($instructor) :
            //get user object
            $instructor_user = get_user_by('email', $instructor);
            if ($instructor_user) :
                echo '<a href="' . get_edit_user_link($instructor_user->ID) . '" target="_blank">' . esc_html($instructor_user->display_name) . '</a>';
            endif;
        elseif($parent_ininstructors) :
            $instructor_names = array();
            foreach($parent_ininstructors as $instructor_user) :
                $instructor_names[] = '<a href="' . get_edit_user_link($instructor_user->ID) . '" target="_blank">' . esc_html($instructor_user->display_name) . '</a>';
            endforeach;
            echo implode(', ', $instructor_names);
        else :
            echo 'No instructor assigned';
        endif;
        echo '</td>';
        
        // Payment Status Column
        echo '<td class="payment-status">';
        switch ($payment_status) {
            case 'paid':
                echo '<span class="payment-status paid">✓ Paid</span>';
                break;
            case 'staff_taught':
                echo '<span class="payment-status staff-taught">👨‍🏫 Staff Taught</span>';
                break;
            case 'no_payment':
                echo '<span class="payment-status no-payment">⊘ No Payment</span>';
                break;
            default:
                echo '<span class="payment-status unpaid">✗ Unpaid</span>';
                break;
        }
        echo '</td>';
        
        // Payment Details Column
        echo '<td class="payment-details">';
        if (in_array($payment_status, ['paid', 'staff_taught'])) {
            if ($payment_amount > 0) {
                echo '<div class="payment-amount">$' . number_format($payment_amount, 2) . '</div>';
            }
            if ($payment_date) {
                $date_label = $payment_status === 'paid' ? 'Paid' : 'Taught';
                echo '<div class="payment-date">' . $date_label . ': ' . date('M j, Y', strtotime($payment_date)) . '</div>';
            }
            if ($payment_notes) {
                echo '<div class="payment-notes" style="font-size: 0.9em; color: #666;">' . esc_html($payment_notes) . '</div>';
            }
        } elseif ($payment_status === 'no_payment') {
            echo '<div class="no-payment-info">No payment required</div>';
            if ($payment_notes) {
                echo '<div class="payment-notes" style="font-size: 0.9em; color: #666;">' . esc_html($payment_notes) . '</div>';
            }
        } else {
            // Show payment form for unpaid events
            echo '<div class="payment-form">';
            echo '<form method="post" style="display: inline-block;">';
            wp_nonce_field('individual_payment_action', 'payment_nonce');
            echo '<input type="hidden" name="event_id" value="' . $sub_event_id . '">';
            echo '<div style="margin-bottom: 5px;">';
            echo '<select name="status" onchange="toggleIndividualPaymentFields(this)" style="width: 140px;">';
            echo '<option value="paid">Mark as Paid</option>';
            echo '<option value="staff_taught">Staff Taught</option>';
            echo '<option value="no_payment">No Payment</option>';
            echo '</select>';
            echo '</div>';
            echo '<div class="payment-fields" style="margin-bottom: 5px;">';
            echo '<input type="number" step="0.01" name="amount" value="' . $payment_amount . '" placeholder="Amount" style="width: 80px;">';
            echo '<input type="date" name="date_paid" value="' . date('Y-m-d') . '" style="width: 120px; margin-left: 5px;">';
            echo '</div>';
            echo '<div style="margin-bottom: 5px;">';
            echo '<input type="text" name="notes" placeholder="Notes (optional)" style="width: 150px;">';
            echo '</div>';
            echo '<input type="submit" name="mark_paid" class="button button-small button-primary" value="Update Status">';
            echo '</form>';
            echo '</div>';
        }
        echo '</td>';
        
        echo '</tr>';
    endwhile;
else :
    echo '<tr><td colspan="10">No past events found.</td></tr>';
endif;
?>

<script>
function toggleIndividualPaymentFields(selectElement) {
    const paymentFields = selectElement.closest('.payment-form').querySelector('.payment-fields');
    const amountField = paymentFields.querySelector('input[name="amount"]');
    const dateField = paymentFields.querySelector('input[name="date_paid"]');
    
    if (selectElement.value === 'no_payment') {
        paymentFields.style.display = 'none';
    } else {
        paymentFields.style.display = 'block';
        
        // Update date field placeholder based on status
        if (selectElement.value === 'paid') {
            dateField.title = 'Date paid';
        } else if (selectElement.value === 'staff_taught') {
            dateField.title = 'Date taught';
        }
    }
}
</script>