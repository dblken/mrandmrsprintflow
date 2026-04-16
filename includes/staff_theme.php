<?php
/**
 * Staff portal palette: primary #06A1A1, soft #9ED7C4.
 * Requires `html.printflow-staff` (script in admin_style.php or header.php for /staff/).
 */
?>
<style>
    html.printflow-staff {
        --accent-color: #06A1A1;
        --staff-primary: #06A1A1;
        --staff-soft: #9ED7C4;
    }

    /* Main area: focus rings & links */
    html.printflow-staff .input-field:focus,
    html.printflow-staff select:focus,
    html.printflow-staff input:focus {
        border-color: var(--staff-primary);
        box-shadow: 0 0 0 3px rgba(6, 161, 161, 0.18);
    }

    html.printflow-staff .btn-primary {
        background: #06A1A1;
        color: #fff;
    }
    html.printflow-staff .btn-primary:hover {
        background: #058f8f;
        box-shadow: 0 4px 14px rgba(6, 161, 161, 0.35);
    }

    /* Sidebar shell */
    html.printflow-staff .sidebar {
        background: linear-gradient(180deg, #011818 0%, #022a2a 24%, #033838 55%, #044040 100%);
        border-right: 1px solid rgba(6, 161, 161, 0.22);
        box-shadow: 4px 0 24px rgba(0, 48, 48, 0.14);
    }
    html.printflow-staff .sidebar-header {
        border-bottom: 1px solid rgba(158, 215, 196, 0.18);
    }
    html.printflow-staff .sidebar-header .logo img {
        border-color: rgba(158, 215, 196, 0.4) !important;
    }
    html.printflow-staff .logo-icon {
        background: linear-gradient(135deg, #035050, #06A1A1);
        border-color: rgba(158, 215, 196, 0.35);
    }
    html.printflow-staff .sidebar-collapse-btn {
        border-color: rgba(6, 161, 161, 0.28);
        color: #9ED7C4;
    }
    html.printflow-staff .sidebar-collapse-btn:hover {
        border-color: rgba(158, 215, 196, 0.45);
        color: #fff;
    }

    html.printflow-staff #mobileBurger {
        background: linear-gradient(135deg, #022e2e, #06A1A1);
        border-color: rgba(158, 215, 196, 0.35);
    }
    html.printflow-staff #mobileBurger:hover {
        background: linear-gradient(135deg, #035f5f, #09b5b5);
        border-color: rgba(158, 215, 196, 0.5);
    }

    html.printflow-staff .nav-section-title {
        color: rgba(158, 215, 196, 0.55);
    }
    html.printflow-staff .nav-item {
        color: rgba(220, 245, 238, 0.9);
    }
    html.printflow-staff .nav-item:hover {
        color: #f6fffc;
    }
    html.printflow-staff .nav-item.active {
        background: linear-gradient(135deg, #f7fefb 0%, #e5f9f2 42%, #d4f0e6 100%);
        color: #023d3d;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.9);
    }
    html.printflow-staff .nav-item.active .nav-icon {
        color: #023d3d;
        stroke: #023d3d;
    }
    html.printflow-staff .nav-item.active:hover {
        background: linear-gradient(135deg, #ffffff 0%, #eefaf5 50%, #dff5ec 100%);
        color: #012828;
    }

    html.printflow-staff .sidebar-footer {
        border-top: 1px solid rgba(6, 161, 161, 0.2);
    }
    html.printflow-staff .user-avatar {
        background: linear-gradient(135deg, #047676 0%, #06A1A1 55%, #9ED7C4 100%);
        border-color: rgba(158, 215, 196, 0.45);
    }

    html.printflow-staff .sidebar.collapsed .nav-item.active .nav-icon {
        color: #023d3d;
        stroke: #023d3d;
    }
    html.printflow-staff .sidebar.collapsed .nav-section-title::after {
        color: rgba(158, 215, 196, 0.5);
    }

    html.printflow-staff .sidebar-nav {
        scrollbar-color: rgba(6, 161, 161, 0.35) transparent;
    }
    html.printflow-staff .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(6, 161, 161, 0.28);
    }
    html.printflow-staff .sidebar-nav:hover::-webkit-scrollbar-thumb {
        background: rgba(6, 161, 161, 0.45);
    }

    /* KPI / stat accents */
    html.printflow-staff .kpi-card::before,
    html.printflow-staff .kpi-card.indigo::before,
    html.printflow-staff .kpi-card.emerald::before,
    html.printflow-staff .kpi-card.amber::before,
    html.printflow-staff .kpi-card.rose::before,
    html.printflow-staff .kpi-card.blue::before,
    html.printflow-staff .kpi-ind::before,
    html.printflow-staff .kpi-em::before,
    html.printflow-staff .kpi-amb::before,
    html.printflow-staff .kpi-vio::before {
        background: linear-gradient(90deg, #035f5f, #06A1A1, #9ED7C4) !important;
    }
    html.printflow-staff .kpi-label,
    html.printflow-staff .kpi-lbl {
        background: linear-gradient(90deg, #023d3d, #06A1A1) !important;
        -webkit-background-clip: text !important;
        background-clip: text !important;
        color: transparent !important;
        -webkit-text-fill-color: transparent !important;
    }

    html.printflow-staff .stats-grid .stat-card::before,
    html.printflow-staff .stat-card:not(.no-stat-accent)::before {
        background: linear-gradient(90deg, #035f5f, #06A1A1, #9ED7C4);
    }

    html.printflow-staff .stat-label {
        color: #047676;
    }

    /* Shared filter / sort controls used across staff pages */
    html.printflow-staff .toolbar-container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        width: 100%;
    }
    html.printflow-staff .toolbar-group {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    html.printflow-staff .toolbar-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 36px;
        padding: 7px 14px;
        border: 1px solid #e5e7eb;
        background: #fff;
        border-radius: 8px;
        color: #374151;
        font-size: 13px;
        font-weight: 600;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
    }
    html.printflow-staff .toolbar-btn:hover {
        background: #f8fafc;
        border-color: #9ca3af;
        color: #0f172a;
    }
    html.printflow-staff .toolbar-btn.active {
        background: rgba(6, 161, 161, 0.1);
        border-color: #06A1A1;
        color: #047676;
        box-shadow: 0 2px 10px rgba(6, 161, 161, 0.12);
    }
    html.printflow-staff .toolbar-btn svg {
        flex: 0 0 auto;
    }
    html.printflow-staff .dropdown-panel,
    html.printflow-staff .sort-dropdown,
    html.printflow-staff .filter-panel {
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.14);
        z-index: 200;
    }
    html.printflow-staff .sort-dropdown {
        min-width: 200px;
        padding: 6px;
        overflow: hidden;
    }
    html.printflow-staff .filter-panel {
        width: min(320px, calc(100vw - 32px));
        overflow: hidden;
    }
    html.printflow-staff .filter-header,
    html.printflow-staff .filter-panel-header {
        padding: 14px 18px;
        border-bottom: 1px solid #f3f4f6;
        color: #111827;
        font-size: 14px;
        font-weight: 800;
    }
    html.printflow-staff .filter-section {
        padding: 14px 18px;
        border-bottom: 1px solid #f3f4f6;
    }
    html.printflow-staff .filter-section:last-of-type {
        border-bottom: 0;
    }
    html.printflow-staff .filter-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 10px;
    }
    html.printflow-staff .filter-label,
    html.printflow-staff .filter-section-label {
        color: #374151;
        font-size: 13px;
        font-weight: 700;
    }
    html.printflow-staff .filter-reset-link,
    html.printflow-staff a.filter-reset-link {
        padding: 0;
        border: 0;
        background: transparent;
        color: #047676;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
        text-decoration: none;
        cursor: pointer;
    }
    html.printflow-staff .filter-reset-link:hover,
    html.printflow-staff a.filter-reset-link:hover {
        color: #035f5f;
        text-decoration: underline;
    }
    html.printflow-staff .filter-input,
    html.printflow-staff .filter-select,
    html.printflow-staff .filter-search-input {
        width: 100%;
        min-width: 0;
        height: 36px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background-color: #fff;
        color: #1f2937;
        font-size: 13px;
        line-height: 1.2;
        box-sizing: border-box;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    html.printflow-staff .filter-input,
    html.printflow-staff .filter-search-input {
        padding: 0 10px;
    }
    html.printflow-staff .filter-select {
        padding: 0 32px 0 10px;
        cursor: pointer;
    }
    html.printflow-staff .filter-input:focus,
    html.printflow-staff .filter-select:focus,
    html.printflow-staff .filter-search-input:focus {
        outline: none;
        border-color: #06A1A1;
        box-shadow: 0 0 0 3px rgba(6, 161, 161, 0.14);
    }
    html.printflow-staff .filter-date-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }
    html.printflow-staff .filter-date-label {
        margin-bottom: 4px;
        color: #6b7280;
        font-size: 11px;
        font-weight: 600;
    }
    html.printflow-staff .filter-footer,
    html.printflow-staff .filter-actions {
        display: flex;
        gap: 8px;
        padding: 14px 18px;
        border-top: 1px solid #f3f4f6;
    }
    html.printflow-staff .filter-btn-reset,
    html.printflow-staff .filter-btn-apply {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 36px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
    }
    html.printflow-staff .filter-btn-reset {
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #374151;
    }
    html.printflow-staff .filter-btn-reset:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
    }
    html.printflow-staff .filter-btn-apply {
        border: 1px solid #06A1A1;
        background: #06A1A1;
        color: #fff;
    }
    html.printflow-staff .filter-btn-apply:hover {
        background: #058f8f;
        border-color: #058f8f;
    }
    html.printflow-staff .filter-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        border-radius: 999px;
        background: #06A1A1;
        color: #fff;
        font-size: 10px;
        font-weight: 800;
        line-height: 1;
    }
    html.printflow-staff .sort-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        width: 100%;
        padding: 9px 12px;
        border-radius: 6px;
        color: #374151;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: background 0.1s ease, color 0.1s ease;
    }
    html.printflow-staff .sort-option:hover {
        background: #f8fafc;
        color: #111827;
    }
    html.printflow-staff .sort-option.active,
    html.printflow-staff .sort-option.selected {
        background: rgba(6, 161, 161, 0.1);
        color: #047676;
    }
    html.printflow-staff .sort-option .check,
    html.printflow-staff .sort-option svg.check {
        color: #06A1A1;
        margin-left: auto;
    }
    html.printflow-staff [x-cloak] {
        display: none !important;
    }

    /* Form guard (sidebar portal) */
    html.printflow-staff .pf-fg-spinner {
        border-color: rgba(6, 161, 161, 0.3);
        border-top-color: #06A1A1;
    }
    html.printflow-staff .pf-fg-save-highlight {
        box-shadow: 0 0 0 2px rgba(6, 161, 161, 0.85) !important;
    }
    html.printflow-staff .pf-fg-btn--accent {
        background: #06A1A1;
        color: #fff;
        border-color: #023d3d;
        box-shadow: 0 2px 10px rgba(6, 161, 161, 0.35);
    }
    html.printflow-staff .pf-fg-btn--accent:hover:not(:disabled) {
        background: #058f8f;
    }
    html.printflow-staff .pf-fg-btn--discard {
        background: #023d3d;
        color: #9ED7C4;
        border-color: #023d3d;
    }
    html.printflow-staff .pf-fg-btn--discard:hover:not(:disabled) {
        background: #035050;
        color: #c8efe0;
    }
    html.printflow-staff .pf-fg-btn--neutral {
        border-color: #06A1A1;
        color: #023d3d;
    }
    html.printflow-staff .pf-fg-btn--neutral:hover:not(:disabled) {
        background: rgba(158, 215, 196, 0.25);
    }
    html.printflow-staff .pf-fg-nav-modal__title,
    html.printflow-staff .pf-fg-nav-modal__sub {
        color: #023d3d;
    }
    html.printflow-staff .pf-fg-nav-modal__list {
        background: linear-gradient(135deg, rgba(158, 215, 196, 0.2), rgba(6, 161, 161, 0.08));
        border-color: rgba(6, 161, 161, 0.35);
        border-left-color: #06A1A1;
    }
    html.printflow-staff .pf-fg-nav-modal__list li::before {
        background: #06A1A1;
    }

    /* Unified Table Action Buttons */
    html.printflow-staff .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 12px;
        min-height: 28px;
        font-size: 12px;
        font-weight: 700;
        border-radius: 6px;
        transition: all 0.2s;
        text-decoration: none;
        border: none;
        cursor: pointer;
    }
    html.printflow-staff .btn-action-primary {
        background: rgba(6, 161, 161, 0.12);
        color: #058f8f;
    }
    html.printflow-staff .btn-action-primary:hover {
        background: #06A1A1;
        color: #ffffff;
        transform: translateY(-1px);
    }
    html.printflow-staff .btn-action-secondary {
        background: rgba(124, 58, 237, 0.1);
        color: #7c3aed;
    }
    html.printflow-staff .btn-action-secondary:hover {
        background: #7c3aed;
        color: #ffffff;
        transform: translateY(-1px);
    }
    html.printflow-staff .btn-action-danger {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }
    html.printflow-staff .btn-action-danger:hover {
        background: #ef4444;
        color: #ffffff;
        transform: translateY(-1px);
    }
</style>
