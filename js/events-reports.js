jQuery(document).ready(function ($) {
  // Function to handle form submission with AJAX
  $("#events-reports-form").on("submit", function (e) {
    e.preventDefault();

    // Show loading indicator
    $(".report-results-container").html(
      '<div class="loading"><p>Loading report data...</p></div>'
    );

    // Get form data
    var formData = $(this).serialize();

    // Submit AJAX request
    $.ajax({
      url: ajaxurl,
      type: "GET",
      data: formData,
      success: function (response) {
        // Replace the results container with the new data
        $(".report-results-container").html(
          $(response).find(".report-results-container").html()
        );
      },
      error: function (xhr, status, error) {
        $(".report-results-container").html(
          '<div class="error"><p>Error loading report data: ' +
            error +
            "</p></div>"
        );
      },
    });
  });

  // Function to export report to CSV
  $(".export-csv").on("click", function (e) {
    e.preventDefault();

    var reportType = $(this).data("report-type");
    var formData =
      $("#events-reports-form").serialize() +
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

    $("#start_date").val(formatDate(startDate));
    $("#end_date").val(formatDate(endDate));

    // Submit the form
    $("#events-reports-form").submit();
  });

  // Function to toggle advanced filters
  $(".toggle-advanced-filters").on("click", function (e) {
    e.preventDefault();
    $(".advanced-filters").slideToggle();
    $(this).text(
      $(this).text() === "Show Advanced Filters"
        ? "Hide Advanced Filters"
        : "Show Advanced Filters"
    );
  });
});
