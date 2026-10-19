
<?php
require_once __DIR__ . '/../koneksi.php';

wajibRole(['owner']);

$namaOwner = $_SESSION['user']['nama_lengkap']
    ?? $_SESSION['user']['username']
    ?? 'Owner';

$halamanAktif = basename($_SERVER['PHP_SELF']);
$inisialOwner = strtoupper(substr($namaOwner, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($judul ?? 'Dashboard Owner') ?> - ParkirKu</title>

    <link rel="stylesheet" href="../style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --owner-green: #123b2e;
            --owner-green-light: #1d5942;
            --owner-yellow: #f3c84b;
            --owner-bg: #f5f7f5;
            --owner-white: #ffffff;
            --owner-text: #25352c;
            --owner-muted: #829087;
            --owner-border: #e7ece8;
            --owner-sidebar-width: 260px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'DM Sans', Arial, sans-serif;
            background: var(--owner-bg);
            color: var(--owner-text);
        }

        body.owner-menu-open {
            overflow: hidden;
        }

        a {
            -webkit-tap-highlight-color: transparent;
        }

        /* SIDEBAR */

        .owner-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            width: var(--owner-sidebar-width);
            padding: 25px 17px 18px;
            overflow-y: auto;
            background: var(--owner-green);
            color: #fff;
            transition: transform .25s ease;
        }

        .owner-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 10px 28px;
            border-bottom: 1px solid rgba(255,255,255,.11);
        }

        .owner-brand-icon {
            display: grid;
            place-items: center;
            width: 43px;
            height: 43px;
            flex-shrink: 0;
            border-radius: 13px;
            background: var(--owner-yellow);
            color: var(--owner-green);
        }

        .owner-brand-icon svg {
            width: 26px;
            height: 26px;
        }

        .owner-brand-name {
            font-family: 'Manrope', sans-serif;
            color: #fff;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -.8px;
        }

        .owner-brand-name span {
            color: var(--owner-yellow);
        }

        .owner-brand-caption {
            margin-top: 3px;
            color: #b6c9bf;
            font-size: 10px;
            letter-spacing: 1.1px;
            text-transform: uppercase;
        }

        .owner-menu-title {
            margin: 26px 11px 10px;
            color: #91ab9d;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.3px;
            text-transform: uppercase;
        }

        .owner-nav {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .owner-sidebar a.owner-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 46px;
            padding: 12px 13px;
            border: 1px solid transparent;
            border-radius: 10px;
            color: #d1dfd7;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: background .2s ease, color .2s ease;
        }

        .owner-link-icon {
            display: grid;
            place-items: center;
            width: 21px;
            height: 21px;
            flex-shrink: 0;
        }

        .owner-link-icon svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
        }

        .owner-sidebar a.owner-link:hover {
            background: rgba(255,255,255,.08);
            color: #fff;
        }

        .owner-sidebar a.owner-link.active {
            background: var(--owner-yellow);
            color: var(--owner-green);
            font-weight: 800;
            box-shadow: 0 5px 13px rgba(0,0,0,.08);
        }

        .owner-sidebar a.owner-link.active::before {
            content: "";
            position: absolute;
            left: -17px;
            top: 11px;
            bottom: 11px;
            width: 4px;
            border-radius: 0 5px 5px 0;
            background: var(--owner-yellow);
        }

        .owner-sidebar a.owner-link.active::before {
            display: none;
        }

        .owner-nav-spacer {
            flex: 1;
            min-height: 25px;
        }

        .owner-sidebar-bottom {
            margin-top: 22px;
            padding-top: 17px;
            border-top: 1px solid rgba(255,255,255,.11);
        }

        .owner-account {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 13px 10px 4px;
        }

        .owner-account-avatar {
            display: grid;
            place-items: center;
            width: 39px;
            height: 39px;
            flex-shrink: 0;
            border: 2px solid rgba(255,255,255,.15);
            border-radius: 12px;
            background: #245a43;
            color: var(--owner-yellow);
            font-weight: 800;
            font-size: 15px;
        }

        .owner-account-info {
            min-width: 0;
        }

        .owner-account-name {
            overflow: hidden;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .owner-account-role {
            margin-top: 4px;
            color: #a9c2b4;
            font-size: 10px;
        }

        .owner-logout {
            margin-top: 13px;
        }

        .owner-sidebar a.owner-link.owner-logout-link:hover {
            background: rgba(248,113,113,.13);
            color: #fecaca;
        }

        /* AREA UTAMA */

        .owner-main {
            min-height: 100vh;
            margin-left: var(--owner-sidebar-width);
            transition: margin-left .25s ease;
        }

        .owner-topbar {
            position: sticky;
            top: 0;
            z-index: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            min-height: 75px;
            padding: 14px 30px;
            border-bottom: 1px solid var(--owner-border);
            background: rgba(255,255,255,.96);
            backdrop-filter: blur(12px);
        }

        .owner-topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .owner-page-heading {
            min-width: 0;
        }

        .owner-topbar-title {
            margin: 0;
            color: var(--owner-green);
            font-family: 'Manrope', sans-serif;
            font-size: 15px;
            font-weight: 800;
        }

        .owner-topbar-subtitle {
            margin-top: 4px;
            color: var(--owner-muted);
            font-size: 11px;
        }

        .owner-topbar-right {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .owner-role-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border: 1px solid #e7eee8;
            border-radius: 9px;
            background: #f8faf8;
            color: var(--owner-green);
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .owner-role-pill::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #2f9b63;
        }

        .owner-topbar-avatar {
            display: grid;
            place-items: center;
            width: 39px;
            height: 39px;
            flex-shrink: 0;
            border-radius: 12px;
            background: var(--owner-green);
            color: var(--owner-yellow);
            font-size: 14px;
            font-weight: 800;
        }

        .owner-content {
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
            padding: 28px 30px;
        }

        /* TOMBOL MOBILE */

        .owner-menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            border: 1px solid var(--owner-border);
            border-radius: 10px;
            background: #fff;
            color: var(--owner-green);
            cursor: pointer;
        }

        .owner-menu-toggle svg {
            width: 22px;
            height: 22px;
        }

        .owner-sidebar-overlay {
            display: none;
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {
            .owner-sidebar {
                transform: translateX(-100%);
            }

            .owner-sidebar.is-open {
                transform: translateX(0);
            }

            .owner-sidebar-overlay.is-visible {
                position: fixed;
                inset: 0;
                z-index: 900;
                display: block;
                background: rgba(10, 28, 20, .52);
                backdrop-filter: blur(2px);
            }

            .owner-main {
                margin-left: 0;
            }

            .owner-menu-toggle {
                display: inline-flex;
            }

            .owner-topbar {
                min-height: 68px;
                padding: 12px 20px;
            }

            .owner-content {
                padding: 23px 20px;
            }
        }

        @media (max-width: 520px) {
            .owner-topbar {
                gap: 10px;
                padding: 12px 14px;
            }

            .owner-topbar-title {
                font-size: 13px;
            }

            .owner-topbar-subtitle {
                font-size: 10px;
            }

            .owner-topbar-right {
                gap: 8px;
            }

            .owner-role-pill {
                padding: 8px;
                font-size: 10px;
            }

            .owner-topbar-avatar {
                width: 34px;
                height: 34px;
                border-radius: 10px;
            }

            .owner-content {
                padding: 18px 13px;
            }
        }
    </style>
</head>

<body>

<aside class="owner-sidebar" id="ownerSidebar">
    <div class="owner-brand">
        <div class="owner-brand-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="18" height="18" rx="5"/>
                <path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>
            </svg>
        </div>

        <div>
            <div class="owner-brand-name">Parkir<span>Ku</span></div>
            <div class="owner-brand-caption">Parking Management</div>
        </div>
    </div>

    <div class="owner-menu-title">Menu Utama</div>

    <nav class="owner-nav">
        <a href="dashboard.php"
           class="owner-link <?= $halamanAktif === 'dashboard.php' ? 'active' : '' ?>">
            <span class="owner-link-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8">
                    <rect x="3" y="3" width="8" height="8" rx="2"/>
                    <rect x="13" y="3" width="8" height="5" rx="2"/>
                    <rect x="13" y="10" width="8" height="11" rx="2"/>
                    <rect x="3" y="13" width="8" height="8" rx="2"/>
                </svg>
            </span>
            Dashboard
        </a>

        <a href="rekap.php"
           class="owner-link <?= $halamanAktif === 'rekap.php' ? 'active' : '' ?>">
            <span class="owner-link-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8">
                    <path d="M5 3h10l4 4v14H5z"/>
                    <path d="M14 3v5h5M8 16l2.5-3 2 2 3-4"/>
                </svg>
            </span>
            Laporan Parkir
        </a>
    </nav>

    <div class="owner-nav-spacer"></div>

    <div class="owner-sidebar-bottom">
        <div class="owner-menu-title" style="margin-top:0">Akun</div>

        <nav class="owner-nav">
            <a href="../logout.php"
               class="owner-link owner-logout-link"
               onclick="return confirm('Apakah Anda yakin ingin logout?')">
                <span class="owner-link-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8">
                        <path d="M10 17l5-5-5-5M15 12H3"/>
                        <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>
                    </svg>
                </span>
                Logout
            </a>
        </nav>

        <div class="owner-account">
            <div class="owner-account-avatar"><?= e($inisialOwner) ?></div>
            <div class="owner-account-info">
                <div class="owner-account-name"><?= e($namaOwner) ?></div>
                <div class="owner-account-role">Pemilik Parkir</div>
            </div>
        </div>
    </div>
</aside>

<div class="owner-sidebar-overlay" id="ownerSidebarOverlay"></div>

<div class="owner-main">
    <header class="owner-topbar">
        <div class="owner-topbar-left">
            <button
                type="button"
                class="owner-menu-toggle"
                id="ownerMenuToggle"
                aria-label="Buka menu navigasi"
                aria-controls="ownerSidebar"
                aria-expanded="false"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <div class="owner-page-heading">
                <h1 class="owner-topbar-title">Sistem Informasi Parkir</h1>
                <div class="owner-topbar-subtitle">
                    Panel pemantauan dan laporan usaha parkir
                </div>
            </div>
        </div>

        <div class="owner-topbar-right">
            <div class="owner-role-pill">Owner</div>
            <div class="owner-topbar-avatar"><?= e($inisialOwner) ?></div>
        </div>
    </header>

    <main class="owner-content">

<script>
(function () {
    const sidebar = document.getElementById('ownerSidebar');
    const toggle = document.getElementById('ownerMenuToggle');
    const overlay = document.getElementById('ownerSidebarOverlay');

    if (!sidebar || !toggle || !overlay) return;

    function bukaMenu() {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-visible');
        document.body.classList.add('owner-menu-open');
        toggle.setAttribute('aria-expanded', 'true');
    }

    function tutupMenu() {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-visible');
        document.body.classList.remove('owner-menu-open');
        toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', function () {
        if (sidebar.classList.contains('is-open')) {
            tutupMenu();
        } else {
            bukaMenu();
        }
    });

    overlay.addEventListener('click', tutupMenu);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            tutupMenu();
        }
    });

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 900) tutupMenu();
        });
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) tutupMenu();
    });
})();
</script>
</body>
</html>
