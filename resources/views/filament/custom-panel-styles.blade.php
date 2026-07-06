<style>
    /*
    |--------------------------------------------------------------------------
    | Fondo general
    |--------------------------------------------------------------------------
    */

    body {
        font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .fi-body {
        background-color: #f8fafc;
    }

    .dark .fi-body {
        background-color: #020617;
    }

    /*
    |--------------------------------------------------------------------------
    | Sidebar diferenciado
    |--------------------------------------------------------------------------
    */

    .fi-sidebar {
        background-color: #f1f5f9 !important;
        border-right: 1px solid #e2e8f0 !important;
    }

    .dark .fi-sidebar {
        background-color: #0f172a !important;
        border-right-color: #1e293b !important;
    }

    .fi-sidebar-header {
        background-color: #ffffff !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }

    .dark .fi-sidebar-header {
        background-color: #0f172a !important;
        border-bottom-color: #1e293b !important;
    }

    .fi-sidebar-group-label {
        font-size: 11px !important;
        font-weight: 800 !important;
        text-transform: uppercase !important;
        letter-spacing: .06em !important;
        color: #64748b !important;
    }

    .dark .fi-sidebar-group-label {
        color: #94a3b8 !important;
    }

    .fi-sidebar-item-button {
        border-radius: 12px !important;
        transition: background-color .15s ease, color .15s ease, box-shadow .15s ease !important;
    }

    .fi-sidebar-item-button:hover {
        background-color: #ffffff !important;
        color: #1d4ed8 !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .06) !important;
    }

    .dark .fi-sidebar-item-button:hover {
        background-color: #1e293b !important;
        color: #93c5fd !important;
    }

    .fi-sidebar-item.fi-active .fi-sidebar-item-button {
        background-color: #eff6ff !important;
        color: #1d4ed8 !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .06) !important;
    }

    .dark .fi-sidebar-item.fi-active .fi-sidebar-item-button {
        background-color: rgba(37, 99, 235, .18) !important;
        color: #bfdbfe !important;
    }

    .fi-sidebar-item-icon {
        color: #94a3b8 !important;
    }

    .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        color: #2563eb !important;
    }

    .dark .fi-sidebar-item-icon {
        color: #64748b !important;
    }

    .dark .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        color: #93c5fd !important;
    }

    /*
    |--------------------------------------------------------------------------
    | Topbar
    |--------------------------------------------------------------------------
    */

    .fi-topbar nav {
        background-color: rgba(255, 255, 255, .92) !important;
        border-bottom: 1px solid #e2e8f0 !important;
        backdrop-filter: blur(12px);
    }

    .dark .fi-topbar nav {
        background-color: rgba(2, 6, 23, .92) !important;
        border-bottom-color: #1e293b !important;
    }

    /*
    |--------------------------------------------------------------------------
    | Secciones y tablas: toque ligero
    |--------------------------------------------------------------------------
    */

    .fi-section,
    .fi-ta-ctn {
        border-radius: 16px !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04) !important;
    }

    .fi-btn {
        border-radius: 12px !important;
        font-weight: 600 !important;
    }

    .fi-header-heading {
        letter-spacing: -0.025em !important;
    }
</style>