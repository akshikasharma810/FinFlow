<?php
session_start(); // Start the session at the very top
include 'header.php'; // Include header
// No db_connect.php here, as this page only displays the form
// Database connection will be in process_contact.php

// Basic protection: Redirect if user not logged in (optional for contact page, but good practice)
if (!isset($_SESSION['user_id'])) {
    // You might decide to allow non-logged-in users to send messages
    // For now, let's keep it accessible, but process_contact.php will check if needed.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - FinFlow</title>
    <!-- ... existing head content ... -->
</head>
<body>

    <!-- Main Navigation Bar (included from header.php) -->

    <!-- Main Content -->
    <div class="container my-5">
        <!-- Page Header -->
        <div class="text-start mb-4">
            <h2>Get in Touch</h2>
            <p class="text-muted">Have a question or feedback? We'd love to hear from you.</p>
        </div>

        <!-- Alert Container for contact form messages -->
        <div id="contactAlertContainer" class="mt-3"></div>

        <!-- Contact Card -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-lg-5">
                <div class="row">
                    <!-- Left Column: Contact Form -->
                    <div class="col-lg-7">
                        <h4 class="mb-4">Send us a Message</h4>
                        <form action="process_contact.php" method="POST"> <!-- IMPORTANT CHANGES HERE -->
                            <div class="mb-3">
                                <label for="fullName" class="form-label">Full Name</label>
                                <input type="text" id="fullName" name="full_name" class="form-control" required> <!-- Added name="full_name" -->
                            </div>
                            <div class="mb-3">
                                <label for="emailAddress" class="form-label">Email Address</label>
                                <input type="email" id="emailAddress" name="email" class="form-control" required> <!-- Added name="email" -->
                            </div>
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" id="subject" name="subject" class="form-control" required> <!-- Added name="subject" -->
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Your message...</label>
                                <textarea id="message" name="message" class="form-control" rows="5" required></textarea> <!-- Added name="message" -->
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Submit Message</button>
                        </form>
                    </div>

                    <!-- Right Column: Contact Information (remains unchanged) -->
                    <div class="col-lg-5 ps-lg-5 mt-5 mt-lg-0">
                        <h4 class="mb-4">Contact Information</h4>
                        <div class="d-flex mb-3">
                            <span class="material-symbols-outlined contact-icon text-primary">mail</span>
                            <div>
                                <strong>Email</strong>
                                <p class="text-muted mb-0">support@finflow.example</p>
                            </div>
                        </div>
                        <div class="d-flex mb-3">
                            <span class="material-symbols-outlined contact-icon text-primary">call</span>
                            <div>
                                <strong>Phone</strong>
                                <p class="text-muted mb-0">+91 98765 43210</p>
                            </div>
                        </div>
                        <div class="d-flex">
                            <span class="material-symbols-outlined contact-icon text-primary">location_on</span>
                            <div>
                                <strong>Address</strong>
                                <p class="text-muted mb-0">FinFlow Towers, MI Road<br>Jaipur, Rajasthan, 302001<br>India</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add a little padding at the bottom -->
    <div class="py-3"></div>

    <!-- Latest compiled JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript for displaying alerts for contact form
        document.addEventListener('DOMContentLoaded', function() {
            // Use PHP to inject status directly for reliability
            var contactAlertContainer = document.getElementById('contactAlertContainer');
            var status = "<?php echo isset($_GET['status']) ? $_GET['status'] : ''; ?>";
            if (contactAlertContainer && status) {
                var message = '';
                var alertType = '';
                if (status === 'success') {
                    message = 'Your message has been sent successfully! We will get back to you soon.';
                    alertType = 'alert-success';
                } else if (status === 'error') {
                    message = 'There was an error sending your message. Please try again later.';
                    alertType = 'alert-danger';
                } else if (status === 'invalid') {
                    message = 'Please fill in all required fields and provide a valid email address.';
                    alertType = 'alert-danger';
                }
                if (message) {
                    contactAlertContainer.innerHTML = `<div class="alert ${alertType} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;
                    setTimeout(function() {
                        var url = new URL(window.location.href);
                        url.searchParams.delete('status');
                        window.history.replaceState({}, document.title, url.toString());
                    }, 5000);
                }
            }
        });
    </script>
</body>
</html>