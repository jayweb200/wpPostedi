jQuery(document).ready(function($) {
    const feedbackContainer = $('#aipu-edit-link-feedback');
    const editFormContainer = $('#aipu-edit-link-form-container');
    const currentUrlInput = $('#edit-link-current-url');
    const anchorTextInput = $('#edit-link-anchor-text');
    const newUrlInput = $('#edit-link-new-url');
    const postIdInput = $('#edit-link-postid');
    const brokenUrlB64Input = $('#edit-link-brokenurl-b64');
    const anchorB64Input = $('#edit-link-anchor-b64');

    let activeRow = null; // To keep track of the table row being edited

    // Edit Link Button Click
    $('.button-edit-link').on('click', function() {
        activeRow = $(this).closest('tr');
        const postId = $(this).data('postid');
        const brokenUrlB64 = $(this).data('brokenurl');
        const anchorB64 = $(this).data('anchor');

        // Decode for display (simple JS atob, assuming UTF-8 which is usually fine for URLs and simple anchors)
        // For complex anchors with special chars, server-side decoding was primary.
        let brokenUrl = '';
        let anchorText = '';
        try {
            brokenUrl = atob(brokenUrlB64);
            anchorText = atob(anchorB64);
        } catch (e) {
            console.error("Error decoding base64 data:", e);
            feedbackContainer.html('<p class="error">Error: Could not decode link data.</p>').show();
            return;
        }


        postIdInput.val(postId);
        brokenUrlB64Input.val(brokenUrlB64); // Keep it base64 for sending back
        anchorB64Input.val(anchorB64); // Keep it base64 for sending back

        currentUrlInput.val(brokenUrl);
        anchorTextInput.val(anchorText);
        newUrlInput.val(brokenUrl); // Pre-fill new URL with old one for easier editing

        feedbackContainer.html('').hide();
        editFormContainer.slideDown();
        newUrlInput.focus();
    });

    // Cancel Edit Button
    $('#aipu-cancel-edit-link').on('click', function() {
        editFormContainer.slideUp();
        feedbackContainer.html('').hide();
        activeRow = null;
    });

    // Save Edited Link Button
    $('#aipu-save-edited-link').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true);
        feedbackContainer.html('<p><em>Saving...</em></p>').show();

        $.ajax({
            url: aipuDashboard.ajax_url,
            type: 'POST',
            data: {
                action: 'aipu_edit_broken_link',
                nonce: aipuDashboard.nonce,
                post_id: postIdInput.val(),
                broken_url_b64: brokenUrlB64Input.val(),
                new_url: newUrlInput.val(),
                anchor_text_b64: anchorB64Input.val()
            },
            success: function(response) {
                if (response.success) {
                    feedbackContainer.html('<p class="updated">' + response.data.message + '</p>');
                    if (activeRow) {
                        // Maybe update the row or remove it if successfully fixed
                        // For now, just remove it as "fixed" implies it's no longer "broken" in this list
                        activeRow.fadeOut(500, function() { $(this).remove(); });
                    }
                    setTimeout(function() {
                        editFormContainer.slideUp();
                        feedbackContainer.html('').hide();
                    }, 1500);
                } else {
                    feedbackContainer.html('<p class="error">' + (response.data.message || 'Error occurred.') + '</p>');
                }
            },
            error: function() {
                feedbackContainer.html('<p class="error">AJAX error. Please try again.</p>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Unlink Button Click
    $('.button-remove-link').on('click', function() {
        if (!confirm('Are you sure you want to remove this link (unlink)? The anchor text will remain.')) {
            return;
        }

        const $button = $(this);
        activeRow = $(this).closest('tr'); // Store the row
        $button.prop('disabled', true);
        // Can add a temporary status in the row itself if preferred
        activeRow.css('opacity', 0.5);


        $.ajax({
            url: aipuDashboard.ajax_url,
            type: 'POST',
            data: {
                action: 'aipu_remove_broken_link',
                nonce: aipuDashboard.nonce,
                post_id: $button.data('postid'),
                broken_url_b64: $button.data('brokenurl'),
                anchor_text_b64: $button.data('anchor')
            },
            success: function(response) {
                if (response.success) {
                    if (activeRow) {
                        activeRow.fadeOut(500, function() { $(this).remove(); });
                    }
                     // Optionally show a global success message if feedbackContainer is not suitable here
                } else {
                    alert('Error: ' + (response.data.message || 'Could not remove link.'));
                    activeRow.css('opacity', 1);
                }
            },
            error: function() {
                alert('AJAX error. Please try again.');
                activeRow.css('opacity', 1);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
});
