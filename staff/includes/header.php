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

        /* High-End Colorful Luxury Light Theme */
        html:not(.dark) {
            color-scheme: light;
        }
        html:not(.dark) body {
            background-color: #f8fafc;
            color: #0f172a;
        }
        html:not(.dark) .bg-slate-950 {
            background-color: #f8fafc !important;
            color: #0f172a !important;
        }
        
        /* Sidebar (Panel Bar) in Light Mode */
        html:not(.dark) aside {
            background-color: #ffffff !important;
            border-color: #e2e8f0 !important;
            box-shadow: 2px 0 16px rgba(0, 0, 0, 0.04) !important;
        }
        html:not(.dark) aside .border-slate-800,
        html:not(.dark) aside .border-slate-800\/80,
        html:not(.dark) aside .border-slate-800\/60 {
            border-color: #f1f5f9 !important;
        }
        html:not(.dark) aside .bg-slate-950\/40,
        html:not(.dark) aside .bg-slate-950\/50,
        html:not(.dark) aside .bg-slate-950\/30 {
            background-color: #f8fafc !important;
        }
        html:not(.dark) aside nav a {
            color: #334155 !important;
        }
        html:not(.dark) aside nav a:hover {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
        }
        /* Active nav item in Light Mode: Beautiful warm luxury amber-gold pill */
        html:not(.dark) aside nav a.active-nav,
        html:not(.dark) aside nav a.bg-gold-500\/15 {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%) !important;
            color: #78350f !important;
            border: 1px solid #f59e0b !important;
            box-shadow: 0 3px 12px -2px rgba(245, 158, 11, 0.3) !important;
            font-weight: 700 !important;
        }
        html:not(.dark) aside nav a.active-nav span,
        html:not(.dark) aside nav a.bg-gold-500\/15 span {
            color: #78350f !important;
        }
        html:not(.dark) aside nav a.active-nav .bg-blue-500\/15,
        html:not(.dark) aside nav a.active-nav .bg-emerald-500\/15,
        html:not(.dark) aside nav a.active-nav .bg-teal-500\/15,
        html:not(.dark) aside nav a.active-nav .bg-pink-500\/15,
        html:not(.dark) aside nav a.active-nav .bg-purple-500\/15,
        html:not(.dark) aside nav a.active-nav .bg-amber-500\/15,
        html:not(.dark) aside nav a.active-nav .bg-indigo-500\/15,
        html:not(.dark) aside nav a.active-nav .bg-gold-500\/20 {
            background-color: #ffffff !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        }

        /* Top Header in Light Mode */
        html:not(.dark) header {
            background-color: #ffffff !important;
            border-color: #e2e8f0 !important;
            box-shadow: 0 1px 4px 0 rgba(0, 0, 0, 0.04) !important;
        }
        html:not(.dark) header h1 {
            color: #0f172a !important;
        }
        html:not(.dark) header .bg-slate-800\/60 {
            background-color: #f1f5f9 !important;
            border-color: #e2e8f0 !important;
            color: #334155 !important;
        }

        /* Cards & Containers in Light Mode */
        html:not(.dark) .bg-slate-900\/70,
        html:not(.dark) .bg-slate-900\/80,
        html:not(.dark) .bg-slate-900\/90,
        html:not(.dark) .bg-slate-900\/95,
        html:not(.dark) .bg-slate-900 {
            background-color: #ffffff !important;
            color: #1e293b !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.03) !important;
        }
        html:not(.dark) .border-slate-800,
        html:not(.dark) .border-slate-800\/80,
        html:not(.dark) .border-slate-800\/60,
        html:not(.dark) .border-slate-700,
        html:not(.dark) .border-slate-700\/80,
        html:not(.dark) .border-slate-700\/60,
        html:not(.dark) .border-slate-700\/50 {
            border-color: #e2e8f0 !important;
        }
        html:not(.dark) .bg-slate-950\/70,
        html:not(.dark) .bg-slate-950\/60,
        html:not(.dark) .bg-slate-950\/50,
        html:not(.dark) .bg-slate-950\/40 {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
        }
        html:not(.dark) .bg-slate-800,
        html:not(.dark) .bg-slate-800\/50,
        html:not(.dark) .bg-slate-800\/60,
        html:not(.dark) .bg-slate-800\/70,
        html:not(.dark) .bg-slate-800\/80 {
            background-color: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
        }

        /* Executive Dark Luxury Banner preserved in Light Mode */
        html:not(.dark) .from-slate-900 {
            background: linear-gradient(135deg, #0b1120 0%, #1e293b 50%, #0f172a 100%) !important;
            border-color: #334155 !important;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25) !important;
        }
        html:not(.dark) .from-slate-900 h2,
        html:not(.dark) .from-slate-900 h3 {
            color: #ffffff !important;
        }
        html:not(.dark) .from-slate-900 p {
            color: #cbd5e1 !important;
        }
        html:not(.dark) .from-slate-900 p span {
            color: #f1f5f9 !important;
        }
        html:not(.dark) .from-slate-900 .text-gold-400 {
            color: #fde047 !important;
        }
        html:not(.dark) .from-slate-900 .bg-gold-500\/10 {
            background-color: rgba(250, 204, 21, 0.15) !important;
            border-color: rgba(250, 204, 21, 0.3) !important;
            color: #fef08a !important;
        }
        html:not(.dark) .from-slate-900 .bg-slate-800 {
            background-color: #1e293b !important;
            border-color: #475569 !important;
            color: #ffffff !important;
        }

        /* Typography & Contrast in Light Mode */
        html:not(.dark) h1:not(.from-slate-900 *),
        html:not(.dark) h2:not(.from-slate-900 *),
        html:not(.dark) h3:not(.from-slate-900 *),
        html:not(.dark) h4:not(.from-slate-900 *) {
            color: #0f172a !important;
        }
        html:not(.dark) .text-white:not(.from-slate-900 *):not(button *):not(button):not(.badge *):not(.badge):not([class*="bg-emerald-"]):not([class*="bg-rose-"]):not([class*="bg-indigo-"]):not([class*="bg-blue-"]):not([class*="bg-purple-"]):not([class*="bg-teal-"]):not([class*="bg-amber-"]):not([class*="bg-red-"]) {
            color: #0f172a !important;
        }
        html:not(.dark) .text-slate-200 { color: #1e293b !important; }
        html:not(.dark) .text-slate-300 { color: #334155 !important; }
        html:not(.dark) .text-slate-400 { color: #475569 !important; }
        html:not(.dark) .text-slate-500 { color: #64748b !important; }

        /* Tables in Light Mode */
        html:not(.dark) table thead,
        html:not(.dark) table thead tr {
            background-color: #f1f5f9 !important;
        }
        html:not(.dark) table thead th {
            color: #334155 !important;
            font-weight: 700 !important;
            border-color: #cbd5e1 !important;
        }
        html:not(.dark) table tbody tr {
            border-color: #f1f5f9 !important;
            color: #1e293b !important;
        }
        html:not(.dark) table tbody tr:nth-child(even) {
            background-color: #fcfdfe !important;
        }
        html:not(.dark) table tbody tr:hover {
            background-color: #f1f5f9 !important;
        }
        html:not(.dark) table td {
            color: #1e293b !important;
        }

        /* Inputs and Selects in Light Mode */
        html:not(.dark) input,
        html:not(.dark) select,
        html:not(.dark) textarea {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        html:not(.dark) input:focus,
        html:not(.dark) select:focus,
        html:not(.dark) textarea:focus {
            border-color: #b45309 !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(180, 83, 9, 0.2) !important;
        }
        html:not(.dark) input::placeholder,
        html:not(.dark) textarea::placeholder {
            color: #94a3b8 !important;
        }

        /* Colorful KPI Accents in Light Mode */
        html:not(.dark) .text-gold-400 { color: #b45309 !important; }
        html:not(.dark) .text-emerald-400 { color: #047857 !important; }
        html:not(.dark) .text-amber-400 { color: #b45309 !important; }
        html:not(.dark) .text-blue-400 { color: #1d4ed8 !important; }
        html:not(.dark) .text-purple-400,
        html:not(.dark) .text-purple-300 { color: #7e22ce !important; }
        html:not(.dark) .text-rose-400,
        html:not(.dark) .text-rose-300 { color: #be123c !important; }

        /* Rich Colorful Badges in Light Mode */
        html:not(.dark) .bg-emerald-500\/15,
        html:not(.dark) .bg-emerald-500\/20 {
            background-color: #d1fae5 !important;
            color: #065f46 !important;
            border-color: #6ee7b7 !important;
            font-weight: 700 !important;
        }
        html:not(.dark) .bg-amber-500\/15,
        html:not(.dark) .bg-amber-500\/20 {
            background-color: #fef3c7 !important;
            color: #92400e !important;
            border-color: #fcd34d !important;
            font-weight: 700 !important;
        }
        html:not(.dark) .bg-rose-500\/15,
        html:not(.dark) .bg-rose-500\/20 {
            background-color: #ffe4e6 !important;
            color: #9f1239 !important;
            border-color: #fda4af !important;
            font-weight: 700 !important;
        }
        html:not(.dark) .bg-purple-500\/15,
        html:not(.dark) .bg-purple-500\/20 {
            background-color: #f3e8ff !important;
            color: #6b21a8 !important;
            border-color: #d8b4fe !important;
            font-weight: 700 !important;
        }
        html:not(.dark) .bg-blue-500\/15,
        html:not(.dark) .bg-blue-500\/20 {
            background-color: #dbeafe !important;
            color: #1e40af !important;
            border-color: #93c5fd !important;
            font-weight: 700 !important;
        }
        html:not(.dark) .bg-gold-500\/15 {
            background-color: #fef3c7 !important;
            color: #78350f !important;
            border-color: #fde68a !important;
            font-weight: 700 !important;
        }

        /* Gold Gradient */
        html:not(.dark) .gold-gradient {
            background: linear-gradient(135deg, #92400e 0%, #b45309 50%, #78350f 100%) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
        }
    </style>
</head>
<body class="h-full flex flex-col font-sans bg-slate-950 text-slate-100 antialiased selection:bg-gold-500 selection:text-slate-950 transition-colors duration-200">
    <div class="flex h-screen overflow-hidden">
