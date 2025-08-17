jQuery(document).ready(function ($) {
  // Initialize date pickers
  $(".datepicker").datepicker({
    dateFormat: "yy-mm-dd",
    changeMonth: true,
    changeYear: true,
  });

  // Toggle comparison date range and time period selector based on report type
  $(".report-type-selector").on("change", function () {
    var reportType = $(this).val();
    if (reportType === "comparison") {
      $(".comparison-date-range").slideDown();
      $(".time-period-selector").slideUp();
    } else if (reportType === "time_based") {
      $(".comparison-date-range").slideUp();
      $(".time-period-selector").slideDown();
    } else {
      $(".comparison-date-range").slideUp();
      $(".time-period-selector").slideUp();
    }
  });

  // Trigger change on page load to set initial state
  $(".report-type-selector").trigger("change");

  // Function to export report to CSV
  $(".export-csv").on("click", function (e) {
    e.preventDefault();

    var reportType = $(this).data("report-type");
    var formData =
      $(".events-reports-form").serialize() +
      "&export=csv&report_type=" +
      reportType;

    window.location.href = ajaxurl + "?" + formData;
  });

  // Function to handle date range presets
  $(".date-preset").on("click", function (e) {
    e.preventDefault();

    var preset = $(this).data("preset");
    var today = new Date();
    var startDate, endDate;

    switch (preset) {
      case "today":
        startDate = today;
        endDate = today;
        break;
      case "yesterday":
        startDate = new Date(today);
        startDate.setDate(today.getDate() - 1);
        endDate = startDate;
        break;
      case "this_week":
        startDate = new Date(today);
        startDate.setDate(today.getDate() - today.getDay());
        endDate = new Date(startDate);
        endDate.setDate(startDate.getDate() + 6);
        break;
      case "last_week":
        startDate = new Date(today);
        startDate.setDate(today.getDate() - today.getDay() - 7);
        endDate = new Date(startDate);
        endDate.setDate(startDate.getDate() + 6);
        break;
      case "this_month":
        startDate = new Date(today.getFullYear(), today.getMonth(), 1);
        endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        break;
      case "last_month":
        startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        endDate = new Date(today.getFullYear(), today.getMonth(), 0);
        break;
      case "this_year":
        startDate = new Date(today.getFullYear(), 0, 1);
        endDate = new Date(today.getFullYear(), 11, 31);
        break;
      case "last_year":
        startDate = new Date(today.getFullYear() - 1, 0, 1);
        endDate = new Date(today.getFullYear() - 1, 11, 31);
        break;
      case "last_30_days":
        startDate = new Date(today);
        startDate.setDate(today.getDate() - 30);
        endDate = today;
        break;
      case "last_90_days":
        startDate = new Date(today);
        startDate.setDate(today.getDate() - 90);
        endDate = today;
        break;
    }

    // Format dates as YYYY-MM-DD
    var formatDate = function (date) {
      var year = date.getFullYear();
      var month = (date.getMonth() + 1).toString().padStart(2, "0");
      var day = date.getDate().toString().padStart(2, "0");
      return year + "-" + month + "-" + day;
    };

    $(".start-date").val(formatDate(startDate));
    $(".end-date").val(formatDate(endDate));

    // Submit the form
    $(".events-reports-form").submit();
  });

  // Function to toggle advanced filters
  $(".toggle-advanced-filters").on("click", function (e) {
    e.preventDefault();
    $(".advanced-filters-section").slideToggle();
    $(this).text(
      $(this).text() === "Show Advanced Filters"
        ? "Hide Advanced Filters"
        : "Show Advanced Filters"
    );
  });
  // Function to handle table column sorting
  $(document).on("click", ".sortable-column", function () {
    console.log("Column clicked"); // Debug log
    var $table = $(this).closest("table");
    var columnIndex = $(this).data("column");
    var sortType = $(this).data("type") || "text";
    var currentDirection = $(this).data("sort-direction") || "asc";
    var newDirection = currentDirection === "asc" ? "desc" : "asc";

    console.log("Column index:", columnIndex); // Debug log
    console.log("Sort type:", sortType); // Debug log

    // Update sort direction data attribute
    $(this).data("sort-direction", newDirection);

    // Remove sort classes from all headers
    $table.find(".sortable-column").removeClass("sort-asc sort-desc");

    // Add appropriate sort class to current header
    $(this).addClass("sort-" + newDirection);

    // Get all rows except the header
    var $rows = $table.find("tbody tr").toArray();

    // Sort rows based on column data
    $rows.sort(function (a, b) {
      var aValue = $(a)
        .find("td:eq(" + columnIndex + ")")
        .text()
        .trim();
      var bValue = $(b)
        .find("td:eq(" + columnIndex + ")")
        .text()
        .trim();

      // Handle empty values
      if (aValue === "" && bValue === "") return 0;
      if (aValue === "") return newDirection === "asc" ? 1 : -1;
      if (bValue === "") return newDirection === "asc" ? -1 : 1;

      // Parse values based on sort type
      if (sortType === "numeric") {
        aValue = parseFloat(aValue.replace(/[$,]/g, "")) || 0;
        bValue = parseFloat(bValue.replace(/[$,]/g, "")) || 0;
      } else if (sortType === "date") {
        // Check if there's a data-date attribute with the actual date value
        var $aCell = $(a).find("td:eq(" + columnIndex + ")");
        var $bCell = $(b).find("td:eq(" + columnIndex + ")");

        var aDataValue = $aCell.data("date");
        var bDataValue = $bCell.data("date");

        // If data-date attribute exists, use it for sorting
        if (aDataValue && bDataValue) {
          aValue = new Date(aDataValue);
          bValue = new Date(bDataValue);
        } else {
          // Fall back to parsing the displayed text
          // Try to parse date in different formats
          // First try "F j, Y" format (e.g., "August 17, 2025")
          aValue = new Date(aValue);
          bValue = new Date(bValue);

          // If that fails, try other formats
          if (isNaN(aValue.getTime())) {
            // Try "l - F j, Y" format (e.g., "Monday - August 17, 2025")
            aValue = new Date(aValue.replace(/.*?-\s*/, ""));
          }

          if (isNaN(bValue.getTime())) {
            // Try "l - F j, Y" format (e.g., "Monday - August 17, 2025")
            bValue = new Date(bValue.replace(/.*?-\s*/, ""));
          }

          // If still invalid, try to parse with Date.parse as fallback
          if (isNaN(aValue.getTime())) {
            aValue = Date.parse(aValue)
              ? new Date(Date.parse(aValue))
              : new Date(0);
          }

          if (isNaN(bValue.getTime())) {
            bValue = Date.parse(bValue)
              ? new Date(Date.parse(bValue))
              : new Date(0);
          }
        }

        console.log(
          "Parsed date A:",
          aValue,
          "from",
          aDataValue ||
            $(a)
              .find("td:eq(" + columnIndex + ")")
              .text()
              .trim()
        );
        console.log(
          "Parsed date B:",
          bValue,
          "from",
          bDataValue ||
            $(b)
              .find("td:eq(" + columnIndex + ")")
              .text()
              .trim()
        );
      }

      // Compare values
      if (aValue < bValue) {
        return newDirection === "asc" ? -1 : 1;
      }
      if (aValue > bValue) {
        return newDirection === "asc" ? 1 : -1;
      }
      return 0;
    });

    // Reorder rows in the table
    $table.find("tbody").empty().append($rows);
  });

  // Auto-sort by date column (ascending) on page load
  $(document).ready(function () {
    // Wait a bit for the table to be fully loaded
    setTimeout(function () {
      $(".sortable-table").each(function () {
        var $table = $(this);
        var $dateHeader = $table
          .find(".sortable-column[data-type='date']")
          .first();

        if ($dateHeader.length > 0) {
          console.log("Auto-sorting by date column");
          var columnIndex = $dateHeader.data("column");

          // Set as ascending sort
          $dateHeader.data("sort-direction", "asc");
          $dateHeader.addClass("sort-asc");

          // Get all rows except the header
          var $rows = $table.find("tbody tr").toArray();

          // Sort rows by date
          $rows.sort(function (a, b) {
            var $aCell = $(a).find("td:eq(" + columnIndex + ")");
            var $bCell = $(b).find("td:eq(" + columnIndex + ")");

            var aDataValue = $aCell.data("date");
            var bDataValue = $bCell.data("date");

            var aValue, bValue;

            // If data-date attribute exists, use it for sorting
            if (aDataValue && bDataValue) {
              aValue = new Date(aDataValue);
              bValue = new Date(bDataValue);
            } else {
              // Fall back to parsing the displayed text
              aValue = $(a)
                .find("td:eq(" + columnIndex + ")")
                .text()
                .trim();
              bValue = $(b)
                .find("td:eq(" + columnIndex + ")")
                .text()
                .trim();

              // Parse dates in different formats
              aValue = new Date(aValue);
              bValue = new Date(bValue);

              // If that fails, try other formats
              if (isNaN(aValue.getTime())) {
                aValue = new Date(aValue.replace(/.*?-\s*/, ""));
              }

              if (isNaN(bValue.getTime())) {
                bValue = new Date(bValue.replace(/.*?-\s*/, ""));
              }

              // If still invalid, try to parse with Date.parse as fallback
              if (isNaN(aValue.getTime())) {
                aValue = Date.parse(aValue)
                  ? new Date(Date.parse(aValue))
                  : new Date(0);
              }

              if (isNaN(bValue.getTime())) {
                bValue = Date.parse(bValue)
                  ? new Date(Date.parse(bValue))
                  : new Date(0);
              }
            }

            console.log(
              "Auto-sort - Parsed date A:",
              aValue,
              "from",
              aDataValue || aValue
            );
            console.log(
              "Auto-sort - Parsed date B:",
              bValue,
              "from",
              bDataValue || bValue
            );

            // Sort ascending
            if (aValue < bValue) return -1;
            if (aValue > bValue) return 1;
            return 0;
          });

          // Reorder rows in the table
          $table.find("tbody").empty().append($rows);
        }
      });
    }, 500);
  });
});
