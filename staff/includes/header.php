<?php
/**
 * Sapphire Aesthetic & Luxury - Header Template
 */
require_once __DIR__ . '/../config/auth.php';
$currentUser = current_user();
$companyName = get_setting('company_name', 'Sapphire Aesthetic & Luxury');
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> | <?= htmlspecialchars($companyName) ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,400&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Theme Script (Prevents flash of wrong theme) -->
    <script>
        if (localStorage.getItem('sapphire_theme') === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    </script>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        serif: ['"Playfair Display"', 'serif'],
                    },
                    colors: {
                        gold: {
                            50: '#fbf9f4',
                            100: '#f6f2e6',
                            200: '#ede1ca',
                            300: '#dfc9a5',
                            400: '#cfae7d',
                            500: '#c5a880',
                            600: '#b48c59',
                            700: '#967043',
                            800: '#7a5a39',
                            900: '#644a31',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #c5a880; }

        .gold-gradient {
            background: linear-gradient(135deg, #dfc9a5 0%, #c5a880 50%, #967043 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Luxury Light Theme Overrides */
        html:not(.dark) body {
            background-color: #f8fafc;
            color: #0f172a;
        }
        html:not(.dark) .bg-slate-950 {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
        }
        html:not(.dark) .bg-slate-900\/70,
        html:not(.dark) .bg-slate-900\/80,
        html:not(.dark) .bg-slate-900\/90,
        html:not(.dark) .bg-slate-900\/95,
        html:not(.dark) .bg-slate-900 {
            background-color: #ffffff !important;
            color: #1e293b !important;
            border-color: #e2e8f0 !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.03);
        }
        html:not(.dark) .bg-slate-950\/40,
        html:not(.dark) .bg-slate-950\/50,
        html:not(.dark) .bg-slate-950\/60,
        html:not(.dark) .bg-slate-950\/70 {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
        }
        html:not(.dark) .bg-slate-800,
        html:not(.dark) .bg-slate-800\/50,
        html:not(.dark) .bg-slate-800\/60,
        html:not(.dark) .bg-slate-800\/80 {
            background-color: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
        }
        html:not(.dark) .border-slate-800,
        html:not(.dark) .border-slate-800\/80,
        html:not(.dark) .border-slate-800\/60,
        html:not(.dark) .border-slate-700 {
            border-color: #e2e8f0 !important;
        }
        /* Preserve white text on dark/colored buttons and badges, but turn card headers to dark slate */
        html:not(.dark) h1.text-white,
        html:not(.dark) h2.text-white,
        html:not(.dark) h3.text-white,
        html:not(.dark) h4.text-white,
        html:not(.dark) td.text-white,
        html:not(.dark) span.text-white:not(button *):not(button):not([class*="bg-"]):not(.badge) {
            color: #0f172a !important;
        }
        html:not(.dark) .text-slate-400 {
            color: #64748b !important;
        }
        html:not(.dark) .text-slate-300 {
            color: #334155 !important;
        }
        html:not(.dark) .text-slate-200 {
            color: #1e293b !important;
        }
        html:not(.dark) .text-slate-500 {
            color: #64748b !important;
        }
        html:not(.dark) input,
        html:not(.dark) select,
        html:not(.dark) textarea {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }
        html:not(.dark) input::placeholder,
        html:not(.dark) textarea::placeholder {
            color: #94a3b8 !important;
        }
        html:not(.dark) table thead {
            background-color: #f1f5f9 !important;
        }
        html:not(.dark) table thead th {
            color: #475569 !important;
            border-color: #e2e8f0 !important;
        }
        html:not(.dark) table tbody tr {
            border-color: #f1f5f9 !important;
        }
        html:not(.dark) table tbody tr:hover {
            background-color: #f8fafc !important;
        }
        html:not(.dark) .gold-gradient {
            background: linear-gradient(135deg, #967043 0%, #b48c59 50%, #7a5a39 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        html:not(.dark) aside {
            background-color: #ffffff !important;
            border-color: #e2e8f0 !important;
        }
        html:not(.dark) aside nav a {
            color: #334155 !important;
        }
        html:not(.dark) aside nav a:hover {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
        }
        html:not(.dark) aside nav a.bg-gold-500\/15 {
            background-color: #fef3c7 !important;
            color: #92400e !important;
            border-color: #fcd34d !important;
        }
        html:not(.dark) aside nav a.bg-gold-500\/15 i {
            color: #b45309 !important;
        }
    </style>
</head>
<body class="h-full flex flex-col font-sans bg-slate-950 text-slate-100 antialiased selection:bg-gold-500 selection:text-slate-950 transition-colors duration-200">
    <div class="flex h-screen overflow-hidden">
