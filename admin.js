jQuery(document).ready(function ($) {
  // Function to handle rate addition
  function addShippingRate(row) {
    var $row = $(row);
    var rateData = {
      action: "add_shipping_rate",
      nonce: wc_custom_shipping_admin.nonce,
      country: $row.find('select[name$="[country]"]').val(),
      postal_code: $row.find('input[name$="[postal_code]"]').val(),
      min_weight: $row.find('input[name$="[min_weight]"]').val(),
      max_weight: $row.find('input[name$="[max_weight]"]').val(),
      standard_fee: $row.find('input[name$="[standard_fee]"]').val(),
      one_day_fee: $row.find('input[name$="[one_day_fee]"]').val(),
    };

    // Validate required fields
    if (!rateData.country) {
      alert("Please select a country.");
      return;
    }

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: rateData,
      success: function (response) {
        if (response.success) {
          // Update the row with the new ID from the database
          $row.attr("data-rate-id", response.data.rate_id);
          alert("Shipping rate added successfully!");
        } else {
          alert("Error: " + (response.data.message || "Unknown error"));
        }
      },
      error: function () {
        alert("Error: Unable to add shipping rate. Please try again.");
      },
    });
  }

  // Function to handle rate deletion
  function confirmAndDeleteRate(button) {
    var $row = $(button).closest("tr");
    var rateId = $row.attr("data-rate-id");

    // Confirm deletion
    if (
      confirm(
        "Are you sure you want to delete this shipping rate?\n\nThis action cannot be undone and will permanently remove the rate from your shipping options."
      )
    ) {
      // If it's a new unsaved rate
      if (!rateId) {
        $row.fadeOut(300, function () {
          $(this).remove();
        });
        return;
      }

      // Send AJAX request to delete rate
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "delete_shipping_rate",
          rate_id: rateId,
          nonce: wc_custom_shipping_admin.nonce,
        },
        success: function (response) {
          if (response.success) {
            $row.fadeOut(300, function () {
              $(this).remove();
            });
            alert("Shipping rate deleted successfully!");
          } else {
            alert("Error: Unable to delete shipping rate.");
          }
        },
        error: function () {
          alert("Error: Unable to delete shipping rate. Please try again.");
        },
      });
    }
  }

  // Function to handle local pickup deletion
  function confirmAndDeleteLocalPickup(button) {
    var $row = $(button).closest("tr");
    var pickupId = $row.attr("data-pickup-id");

    // Confirm deletion
    if (
      confirm(
        "Are you sure you want to delete this local pickup location?\n\nThis action cannot be undone and will permanently remove the pickup location."
      )
    ) {
      // If it's a new unsaved pickup location
      if (!pickupId) {
        $row.fadeOut(300, function () {
          $(this).remove();
        });
        return;
      }

      // Send AJAX request to delete pickup location
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "delete_local_pickup_location",
          pickup_id: pickupId,
          nonce: wc_custom_shipping_admin.nonce,
        },
        success: function (response) {
          if (response.success) {
            $row.fadeOut(300, function () {
              $(this).remove();
            });
            alert("Local pickup location deleted successfully!");
          } else {
            alert("Error: Unable to delete local pickup location.");
          }
        },
        error: function () {
          alert(
            "Error: Unable to delete local pickup location. Please try again."
          );
        },
      });
    }
  }

  // Event delegation for add rate button
  $(document).on("click", ".add_rate", function (e) {
    e.preventDefault();
    var $row = $(this).closest("tr");

    // Confirm addition
    if (confirm("Do you want to add this shipping rate?")) {
      addShippingRate($row);

      // Clone and reset the new rate row
      var $clone = $row.clone();
      $clone.find("input, select").val("");
      $clone.find("select").prop("selectedIndex", 0);
      $row.before($clone);
    }
  });

  // Event delegation for remove rate button
  $(document).on("click", ".remove_rate", function (e) {
    e.preventDefault();
    confirmAndDeleteRate(this);
  });

  // Event delegation for remove local pickup button
  $(document).on("click", ".remove_local_pickup", function (e) {
    e.preventDefault();
    confirmAndDeleteLocalPickup(this);
  });
});
