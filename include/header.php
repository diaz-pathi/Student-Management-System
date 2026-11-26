<?php
// Page එක තියෙන folder එක අනුව CSS & Icon paths auto සැකසේ
$css_path = file_exists('css/style.css') ? 'css/style.css' : '../css/style.css';
$favicon_path = file_exists('images/favicon.jpg') ? 'images/favicon.jpg' : '../images/favicon.jpg';
$is_dark = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark');
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $is_dark ? 'darkmode' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management System - NVTI BADDEGAMA</title>
    <link rel="icon" type="image/png" href="<?php echo $favicon_path; ?>">

    <!-- Google Fonts: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,600&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="<?php echo $css_path; ?>">
</head>
<body class="<?php echo $is_dark ? 'darkmode' : ''; ?>">
