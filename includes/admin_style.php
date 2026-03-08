<style>
    /* Admin White Theme - Consistent Clean Design */
    :root {
        --bg-color: #ffffff;
        --text-main: #1f2937;
        --text-muted: #6b7280;
        --border-color: #f3f4f6;
        --border-hover: #e5e7eb;
        --accent-color: #3b82f6;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body { 
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; 
        background: var(--bg-color); 
        color: var(--text-main);
    }
    
    /* Layout */
    .dashboard-container { display: flex; min-height: 100vh; }
    .main-content { flex: 1; margin-left: 240px; overflow-y: auto; }
    
    /* Common Headers */
    .top-bar, header { 
        background: var(--bg-color); 
        padding: 24px 32px; /* Increased top/bottom padding to match dashboard look */
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        /* position: sticky;  <-- Removed sticky */
        /* top: 0; */
        /* z-index: 10; */
        margin-bottom: 8px;
    }
    
    .page-title, h1, h2 { font-size: 24px; font-weight: 600; color: var(--text-main); }
    
    .content-area, main { padding: 0 32px 32px 32px; }
    
    /* Cards */
    .card, .stat-card, .chart-card { 
        background: white; 
        border-radius: 16px; 
        padding: 24px; 
        border: 1px solid var(--border-color);
        box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
        transition: all 0.2s; 
        margin-bottom: 24px;
    }
    
    .card:hover, .stat-card:hover, .chart-card:hover { 
        border-color: var(--border-hover); 
        box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
    }
    
    /* Inputs & Forms */
    .input-field, select, input[type="text"], input[type="email"], input[type="password"], input[type="number"], input[type="search"] {
        width: 100%;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 14px;
        transition: all 0.2s;
        color: var(--text-main);
    }
    
    .input-field:focus, select:focus, input:focus {
        border-color: var(--accent-color);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }
    
    label { display: block; font-size: 13px; font-weight: 500; color: var(--text-main); margin-bottom: 6px; }
    
    /* Buttons */
    .btn-primary {
        background: #1f2937; 
        color: white;
        border-radius: 8px;
        padding: 10px 20px;
        font-size: 14px;
        font-weight: 500;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    
    .btn-primary:hover { background: #111827; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    
    .btn-secondary {
        background: white;
        color: var(--text-main);
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px 20px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    
    .btn-secondary:hover { background: #f9fafb; border-color: #d1d5db; }

    /* Tables */
    table { width: 100%; border-collapse: separate; border-spacing: 0; }
    th { text-align: left; padding: 12px 16px; font-size: 13px; color: var(--text-muted); font-weight: 600; border-bottom: 1px solid var(--border-color); }
    td { padding: 16px; font-size: 14px; border-bottom: 1px solid var(--border-color); color: var(--text-main); }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background-color: #fcfcfc; }
    
    /* Utilities */
    .badge { display: inline-flex; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
    .text-sm { font-size: 13px; }
    .text-gray-500 { color: var(--text-muted); }
    .mb-6 { margin-bottom: 24px; }
    .mb-4 { margin-bottom: 16px; }
    .grid { display: grid; gap: 24px; }
    .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
    .grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
    .grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
    
    /* Stats Grid - Single row on most screens */
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 32px; }
    
    /* Dynamic column count based on number of children */
    .stats-grid:has(> :last-child:nth-child(3)) { grid-template-columns: repeat(3, 1fr); }
    
    @media (max-width: 1200px) { .stats-grid { gap: 16px; } }
    @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }
    
    @media (max-width: 1024px) {
        .grid-cols-4, .grid-cols-3 { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .grid-cols-2, .grid-cols-3, .grid-cols-4 { grid-template-columns: 1fr; }
        .main-content { margin-left: 0; padding-top: 0; }
        .sidebar { transform: translateX(-100%); }
        .sidebar.active { transform: translateX(0); box-shadow: 4px 0 15px rgba(0,0,0,0.2); }
        .sidebar.collapsed { width: 240px; }
        .sidebar.collapsed.active { transform: translateX(0); }
        
        /* Show mobile burger menu */
        #mobileBurger { display: flex; }
        
        /* Hide collapse button on mobile */
        .sidebar-collapse-btn { display: none; }
        
        /* Ensure proper z-index stacking */
        .sidebar { z-index: 100; }
        #mobileBurger { z-index: 101; }
        #sidebarOverlay { z-index: 90; }
        
        /* Adjust content padding for mobile */
        .content-area, main { padding: 16px; }
        .top-bar, header { padding: 16px; margin-bottom: 8px; }
        
        /* Add top padding to headers to avoid burger overlap */
        .page-title, h1 { padding-left: 60px; }
        
        /* Make tables horizontally scrollable */
        .overflow-x-auto { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        
        /* Adjust KPI grid for mobile */
        .kpi-row { grid-template-columns: 1fr; gap: 12px; }
    }

    /* Sidebar Styles (Moved from sidebar.php for consistency) */
    .sidebar { width: 240px; background: #fff; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; position: fixed; height: 100vh; top: 0; left: 0; z-index: 50; transition: width 0.3s ease, transform 0.3s ease; }
    .sidebar-header { padding: 24px 20px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; position: relative; }
    .logo { display: flex; align-items: center; gap: 8px; font-size: 18px; font-weight: 600; color: #1f2937; text-decoration: none; overflow: hidden; white-space: nowrap; flex: 1; }
    .logo-icon { min-width: 32px; width: 32px; height: 32px; background: #10b981; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
    
    /* Sidebar Collapse Button */
    .sidebar-collapse-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 6px;
        background: #f9fafb;
        border: none;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .sidebar-collapse-btn:hover {
        background: #e5e7eb;
        color: #374151;
    }
    .sidebar-collapse-btn svg {
        width: 16px;
        height: 16px;
    }
    
    /* Mobile Burger Menu */
    #mobileBurger {
        display: none;
        position: fixed;
        top: 16px;
        left: 16px;
        z-index: 60;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: white;
        border: 1px solid #e5e7eb;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: all 0.2s;
    }
    #mobileBurger:hover {
        background: #f9fafb;
        box-shadow: 0 6px 8px rgba(0,0,0,0.15);
    }
    
    /* Mobile Sidebar Overlay */
    #sidebarOverlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 90;
        opacity: 0;
        transition: opacity 0.3s;
    }
    #sidebarOverlay.active {
        display: block;
        opacity: 1;
    }
    
    .sidebar-nav { flex: 1; overflow-y: auto; padding: 16px 0; }
    .nav-section { margin-bottom: 24px; }
    .nav-section-title { font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; padding: 0 20px; margin-bottom: 8px; }
    .nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 20px; color: #6b7280; font-size: 14px; text-decoration: none; transition: all 0.2s; position: relative; }
    .nav-item:hover { background: #f9fafb; color: #1f2937; }
    .nav-item.active { background: #ecfdf5; color: #10b981; font-weight: 500; border-right: 3px solid #10b981; }
    .nav-icon { width: 20px; height: 20px; }
    .nav-badge { 
        margin-left: auto; 
        background: #ef4444; 
        color: white; 
        font-size: 11px; 
        font-weight: 700; 
        padding: 2px 6px; 
        border-radius: 10px; 
        min-width: 20px; 
        text-align: center;
        line-height: 1.4;
    }
    
    .sidebar-footer { padding: 16px; border-top: 1px solid #e5e7eb; display: flex; flex-direction: column; gap: 8px; }
    .user-profile { display: flex; align-items: center; gap: 12px; padding: 8px; }
    .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px; }
    .user-info { flex: 1; }
    .user-name-display { font-size: 14px; font-weight: 500; color: #1f2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }
    .user-role { font-size: 12px; color: #9ca3af; }
    .logout-btn-footer { 
        display: flex; 
        align-items: center; 
        justify-content: center;
        gap: 8px; 
        padding: 8px 12px; 
        color: #6b7280; 
        font-size: 14px; 
        background: transparent;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
    }
    .logout-btn-footer:hover { 
        color: #ef4444; 
        background: #fef2f2; 
        border-color: #fecaca;
    }
    .logout-btn { display: flex; align-items: center; gap: 8px; padding: 8px 12px; color: #6b7280; font-size: 14px; text-decoration: none; margin-top: 8px; border-radius: 6px; }
    .logout-btn:hover { color: #1f2937; background: #f9fafb; }
    a.user-profile:hover { background: #f9fafb; }

    /* Collapsible Sidebar Support */
    .sidebar.collapsed { width: 72px; }
    .sidebar.collapsed ~ .main-content { margin-left: 72px; }
    
    .sidebar.collapsed .sidebar-header { padding: 24px 12px; justify-content: center; flex-direction: column; gap: 12px; }
    .sidebar.collapsed .logo { flex-direction: column; gap: 4px; }
    .sidebar.collapsed .logo span { display: none; }
    .sidebar.collapsed .sidebar-collapse-btn { margin: 0; }
    .sidebar.collapsed .nav-section-title { text-align: center; font-size: 0; padding: 0; margin-bottom: 16px; }
    .sidebar.collapsed .nav-section-title::after { content: "•••"; font-size: 12px; letter-spacing: 2px; }
    
    .sidebar.collapsed .nav-item { padding: 12px; justify-content: center; margin: 0 8px; border-radius: 8px; border-right: none; }
    .sidebar.collapsed .nav-item.active { border-right: none; background: #ecfdf5; }
    .sidebar.collapsed .nav-item text,
    .sidebar.collapsed .nav-item tooltip,
    .sidebar-nav a { position: relative; }

    /* Only hide the text label itself, preserving the flex icon */
    .sidebar.collapsed .nav-item { font-size: 0; }
    .sidebar.collapsed .nav-icon { margin: 0; width: 20px; height: 20px; flex-shrink: 0; }
    .sidebar.collapsed .nav-badge { 
        position: absolute; 
        top: 8px; 
        right: 8px; 
        min-width: 8px; 
        height: 8px; 
        padding: 0; 
        font-size: 0; 
        border-radius: 50%;
    }

    .sidebar.collapsed .sidebar-footer { padding: 16px 8px; display: flex; flex-direction: column; align-items: center; gap: 8px; }
    .sidebar.collapsed .user-info { display: none; }
    .sidebar.collapsed .user-avatar { margin: 0; }
    .sidebar.collapsed .logout-btn-footer { 
        width: auto;
        padding: 10px;
        border-radius: 8px;
    }
    .sidebar.collapsed .logout-btn-footer span { display: none; }
    .sidebar.collapsed .logout-btn-footer svg { margin: 0; }

    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #d1d5db; }
    
    /* Hide scrollbar for sidebar nav but allow scroll */
    .sidebar-nav { scrollbar-width: thin; scrollbar-color: #f3f4f6 transparent; }
    .sidebar-nav::-webkit-scrollbar-thumb { background: #f3f4f6; }
    .sidebar-nav:hover::-webkit-scrollbar-thumb { background: #e5e7eb; }
    
    /* Strict Layout Enforcement */
    html, body { height: 100%; overflow: hidden; } /* Lock body scroll */
    .dashboard-container { height: 100%; overflow: hidden; }
    .main-content { height: 100%; overflow-y: scroll; scroll-behavior: smooth; } /* Always show scrollbar track */
</style>
