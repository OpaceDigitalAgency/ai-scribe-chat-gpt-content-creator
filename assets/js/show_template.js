(function ($) {
    $(document).ready(function () {
        // Handle DELETE
        $(".delete").click(function (e) {
            e.preventDefault(); 
            const id = $(this).attr("data-id");

            // Debug: confirm the click handler is firing
            if (window.aiScribeDebugMode) console.log("Delete click event triggered! ID =", id);

            // If you want to show/hide a loader for this specific ID
            // you might do something like:
            let loaderSelector = "#loader-img-" + id; 
            
            // If you don't have separate loaders for each ID, 
            // just adjust accordingly or remove loader logic.

            // Make sure you're referencing the correct variable here,
            // not `imgId`.
            $(loaderSelector).show();

            const confirmDelete = confirm("Are you sure you want to delete?");
            if (!confirmDelete) {
                $(loaderSelector).hide();
                return; // If user cancels, stop
            }

            // Debug: ensure the global is accessible
            if (window.aiScribeDebugMode) console.log("ai_scribe =", ai_scribe);
            const link  = ai_scribe.ajaxUrl;
            const nonce = ai_scribe.nonce; 

            // Debug: log the data we’re sending
            if (window.aiScribeDebugMode) console.log("Sending AJAX request to:", link);
            if (window.aiScribeDebugMode) console.log("Data:", {
                action: "al_scribe_remove_short_code_content",
                id: id,
                security: nonce,
            });

            $.ajax({
                type: "POST",
                url: link,
                data: {
                    action: "al_scribe_remove_short_code_content",
                    id: id,
                    security: nonce,
                },
                success: function (response) {
                    // Debug: see the server's JSON response 
                    if (window.aiScribeDebugMode) console.log("AJAX success, response:", response);

                    $(loaderSelector).hide();
                    if (response.success) {
                        // We reload here, which will cause the page to refresh
                        // If you want to see the success message for debugging, 
                        // temporarily comment out this reload line.
                        location.reload();
                    } else {
                        alert("Failed to delete: " + response.data.msg);
                    }
                },
                error: function (xhr, status, error) {
                    // Debug: see if we have any network or server issues
                    if (window.aiScribeDebugMode) console.error("AJAX error:", status, error);
                    if (window.aiScribeDebugMode) console.log("Full response:", xhr.responseText);
                    $(loaderSelector).hide();
                    alert("An error occurred while deleting the shortcode.");
                },
            });
        });

        // Example of "Fetch Template" AJAX (from your snippet)
        $(".fetch-template").click(function () {
            let templateId = $("#template-id").val();

            // Quick validation example
            if (!/^\d+$/.test(templateId)) {
                alert("Invalid Template ID. Please enter a valid number.");
                return;
            }

            $.ajax({
                type: "POST",
                url: ai_scribe_vars.ajaxUrl,
                data: {
                    action: "article_builder_generate_data",
                    template_id: templateId,
                    security: ai_scribe_vars.nonce,
                },
                success: function (response) {
                    if (response) {
                        $("#article-content").html(response);
                    } else {
                        alert("No content found for the provided template ID.");
                    }
                },
                error: function () {
                    alert("An error occurred while fetching the template data.");
                },
            });
        });
    });
})(jQuery);
