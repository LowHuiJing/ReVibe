<?php
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");

    // Error handling
    ini_set('display_errors', 1);  // show errors for debugging
    ini_set('log_errors', 1);
    error_reporting(E_ALL);

    $host     = "localhost";
    $user     = "root";
    $password = "";
    $database = "revibe";

    $conn = mysqli_connect($host, $user, $password, $database);
    if (!$conn) {
        die("<p style='padding:40px;font-family:sans-serif;color:red;'>
            Database connection failed: " . mysqli_connect_error() . "<br>
            Make sure XAMPP MySQL is running and the database <strong>revibe_db</strong> exists.
        </p>");
    }
    mysqli_set_charset($conn, "utf8");

    // Auto-complete orders delivered 7+ days ago with no active return request
    mysqli_query($conn,
        "UPDATE orders o
         JOIN deliveries d ON d.order_id = o.id
         SET o.status = 'completed'
         WHERE o.status = 'delivered'
           AND d.status = 'delivered'
           AND d.estimated_date IS NOT NULL
           AND DATEDIFF(NOW(), d.estimated_date) >= 7
           AND o.id NOT IN (
               SELECT order_id FROM return_requests
               WHERE status NOT IN ('rejected','cancelled')
           )"
    );