import sys

with open('staff/orders.php', 'r', encoding='utf-8') as f:
    content = f.read()

start_marker = '/* ═══════════════════════════════════════════════\n           MOBILE FIXES — Staff Orders Page'
end_marker = '.om-card {\n            background: #f8fafc; border: 1px solid #e2e8f0;'

start_idx = content.find(start_marker)
end_idx = content.find(end_marker)

if start_idx == -1 or end_idx == -1:
    print('Markers not found')
    print('start', start_idx, 'end', end_idx)
    sys.exit(1)

new_css = """/* ═══════════════════════════════════════════════
           MOBILE FIXES — Staff Orders Page
           Target: iPhone / small screens (≤768px)
           ─────────────────────────────────────────────
           RULE: Nothing must ever overflow the viewport.
           All long text uses ellipsis. View + Message
           buttons are ALWAYS visible side-by-side.
        ═══════════════════════════════════════════════ */
        @media (max-width: 768px) {

            /* 1. Stop horizontal scroll at the ROOT level */
            html, body { max-width: 100vw !important; overflow-x: hidden !important; }
            .dashboard-container, .main-content { max-width: 100vw !important; overflow-x: hidden !important; }

            .main-content header {
                padding: 14px 12px !important; flex-direction: column !important;
                align-items: flex-start !important; gap: 8px !important; margin-bottom: 4px !important;
                max-width: 100vw !important; box-sizing: border-box !important;
            }
            #mobileBurger { position: static !important; margin-bottom: 0 !important; }
            .page-title { font-size: 18px !important; }

            .main-content main {
                padding: 0 10px 24px !important; max-width: 100vw !important;
                overflow-x: hidden !important; box-sizing: border-box !important;
            }

            /* 2. KPI: 2 columns */
            .kpi-row { display: grid !important; grid-template-columns: repeat(2, 1fr) !important; gap: 8px !important; margin-bottom: 14px !important; }
            .kpi-card { padding: 10px 12px !important; border-radius: 10px !important; min-width: 0 !important; width: 100% !important; box-sizing: border-box !important; overflow: hidden !important; }
            .kpi-card .kpi-value { font-size: 18px !important; line-height: 1.2 !important; word-break: break-all !important; }
            .kpi-card .kpi-label, .kpi-card .kpi-sub { font-size: 9px !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; display: block !important; }

            /* 3. Toolbar */
            .toolbar-container { flex-direction: row !important; align-items: center !important; justify-content: space-between !important; gap: 8px !important; flex-wrap: nowrap !important; }
            .toolbar-group--title { flex: 1 1 auto !important; min-width: 0 !important; }
            .toolbar-group--actions { flex: 0 0 auto !important; display: flex !important; gap: 6px !important; }
            .toolbar-btn { padding: 6px 10px !important; font-size: 12px !important; gap: 4px !important; }

            /* 5. Card wrapper */
            .staff-orders-table-card { padding: 10px !important; overflow: hidden !important; max-width: 100vw !important; width: 100% !important; box-sizing: border-box !important; }

            /* 6. Table */
            html.printflow-staff .orders-table, .orders-table { display: block !important; width: 100% !important; max-width: 100% !important; min-width: 0 !important; overflow: hidden !important; box-sizing: border-box !important; }
            html.printflow-staff .orders-table thead, .orders-table thead { display: none !important; }
            html.printflow-staff .orders-table tbody, .orders-table tbody { display: block !important; width: 100% !important; max-width: 100% !important; min-width: 0 !important; overflow: hidden !important; }

            /* 7. Row */
            html.printflow-staff .orders-table tr, .orders-table tr { display: flex !important; flex-direction: column !important; width: 100% !important; max-width: 100% !important; min-width: 0 !important; box-sizing: border-box !important; margin-bottom: 10px !important; border: 1px solid #e2e8f0 !important; border-radius: 10px !important; background: #fff !important; overflow: hidden !important; padding: 0 !important; gap: 0 !important; }

            /* 8. Cells */
            html.printflow-staff .orders-table td, .orders-table td { display: flex !important; align-items: center !important; width: 100% !important; max-width: 100% !important; min-width: 0 !important; box-sizing: border-box !important; padding: 6px 12px !important; border-bottom: 1px solid #f1f5f9 !important; overflow: hidden !important; font-size: 12px !important; color: #374151 !important; }
            html.printflow-staff .orders-table td:last-child, .orders-table td:last-child { border-bottom: none !important; }

            /* 9. Order ID */
            html.printflow-staff .orders-table td:first-child, .orders-table td:first-child { order: 0 !important; background: #f8fafc !important; padding: 8px 12px !important; font-weight: 700 !important; color: #1e293b !important; gap: 6px !important; }
            .orders-table td:first-child::before { content: none !important; }
            .orders-table td:first-child .row-indicator { top: 0 !important; bottom: 0 !important; left: 0 !important; width: 3px !important; border-radius: 0 !important; opacity: 1 !important; }

            /* 10. Product name */
            html.printflow-staff .orders-table td:nth-child(2), .orders-table td:nth-child(2) { order: 1 !important; padding: 7px 12px !important; overflow: hidden !important; }
            html.printflow-staff .orders-table td:nth-child(2)::before, .orders-table td:nth-child(2)::before { display: none !important; }
            html.printflow-staff .orders-table td:nth-child(2) .table-text-main, .orders-table td:nth-child(2) .table-text-main { font-size: 12px !important; font-weight: 600 !important; color: #111827 !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; max-width: 220px !important; width: 100% !important; display: block !important; }

            /* 11. Customer name */
            html.printflow-staff .orders-table td:nth-child(3), .orders-table td:nth-child(3) { order: 2 !important; overflow: hidden !important; }
            html.printflow-staff .orders-table td:nth-child(3)::before, .orders-table td:nth-child(3)::before { content: "Customer  " !important; font-size: 9px !important; font-weight: 700 !important; text-transform: uppercase !important; color: #94a3b8 !important; flex-shrink: 0 !important; white-space: nowrap !important; margin-right: 4px !important; }
            html.printflow-staff .orders-table td:nth-child(3) .table-text-main, .orders-table td:nth-child(3) .table-text-main { white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; max-width: 160px !important; width: 100% !important; display: block !important; }

            /* 12. HIDE Source and Date */
            html.printflow-staff .orders-table td:nth-child(4), .orders-table td:nth-child(4), html.printflow-staff .orders-table td:nth-child(5), .orders-table td:nth-child(5) { display: none !important; }

            /* 13. Amount */
            html.printflow-staff .orders-table td:nth-child(6), .orders-table td:nth-child(6) { order: 3 !important; overflow: hidden !important; }
            .orders-table td:nth-child(6)::before { content: "Amount  " !important; font-size: 9px !important; font-weight: 700 !important; text-transform: uppercase !important; color: #94a3b8 !important; flex-shrink: 0 !important; white-space: nowrap !important; margin-right: 4px !important; }

            /* 14. Status */
            html.printflow-staff .orders-table td.status-col-cell, .orders-table td.status-col-cell { order: 4 !important; justify-content: flex-start !important; gap: 6px !important; overflow: hidden !important; }
            .orders-table td.status-col-cell::before { content: "Status  " !important; font-size: 9px !important; font-weight: 700 !important; text-transform: uppercase !important; color: #94a3b8 !important; flex-shrink: 0 !important; white-space: nowrap !important; margin-right: 4px !important; }

            /* 15. Action buttons */
            html.printflow-staff .orders-table td.action-col-cell, .orders-table td.action-col-cell { order: 10 !important; padding: 8px 10px !important; border-top: 1px solid #e8eef3 !important; border-bottom: none !important; overflow: visible !important; }
            .orders-table td.action-col-cell::before { display: none !important; }
            .action-cell { display: flex !important; width: 100% !important; gap: 6px !important; flex-wrap: nowrap !important; }
            html.printflow-staff .orders-table .action-cell .table-action-btn, html.printflow-staff .orders-table .action-cell a.table-action-btn, .orders-table .action-cell .table-action-btn, .orders-table .action-cell a.table-action-btn { display: inline-flex !important; align-items: center !important; justify-content: center !important; flex: 1 1 0 !important; width: calc(50% - 3px) !important; max-width: calc(50% - 3px) !important; min-width: 0 !important; padding: 8px 4px !important; font-size: 12px !important; font-weight: 600 !important; border-radius: 8px !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; min-height: 36px !important; box-sizing: border-box !important; }
        }

        """

new_content = content[:start_idx] + new_css + content[end_idx:]

with open('staff/orders.php', 'w', encoding='utf-8') as f:
    f.write(new_content)

print('Success')
