/**
 * Backstage Dashboard Enhancement Script
 * Enhances the Customer Dashboard at breyya.com/backstage/dashboard/
 * 
 * Features:
 * - Adds new KPI rows to Weekly Summary table
 * - Adds % Change column to all rows
 * - Enhances Recent Customer Notes section
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        checkInterval: 500, // Check every 500ms for dashboard to render
        maxAttempts: 40,    // Give up after 20 seconds (40 * 500ms)
        selectors: {
            weeklyTable: 'table', // Will need to be more specific based on actual DOM
            tableBody: 'tbody',
            tableHeader: 'thead',
            customerNotes: '[data-testid="customer-notes"]' // Placeholder selector
        }
    };

    // Dark theme colors to match existing design
    const THEME = {
        background: '#1a1a2e',
        textPrimary: '#ffffff',
        textSecondary: '#a0a0a0',
        success: '#4ade80',
        danger: '#ef4444',
        border: '#374151'
    };

    let attempts = 0;
    let enhancementComplete = false;

    /**
     * Check if we're on the dashboard page
     */
    function isDashboardPage() {
        return window.location.pathname.includes('/backstage/dashboard/');
    }

    /**
     * Wait for the dashboard to render and find the weekly summary table
     */
    function waitForDashboard() {
        attempts++;
        
        if (attempts > CONFIG.maxAttempts) {
            console.log('[Dashboard Enhancer] Timeout waiting for dashboard to render');
            return;
        }

        const weeklyTable = findWeeklyTable();
        if (weeklyTable && !enhancementComplete) {
            console.log('[Dashboard Enhancer] Dashboard found, applying enhancements...');
            enhanceDashboard(weeklyTable);
            enhancementComplete = true;
        } else {
            setTimeout(waitForDashboard, CONFIG.checkInterval);
        }
    }

    /**
     * Find the weekly summary table (may need adjustment based on actual DOM)
     */
    function findWeeklyTable() {
        // Look for table that contains Revenue, Tips, etc.
        const tables = document.querySelectorAll('table');
        for (let table of tables) {
            const text = table.textContent.toLowerCase();
            if (text.includes('revenue') && text.includes('tips') && text.includes('this week')) {
                return table;
            }
        }
        return null;
    }

    /**
     * Main enhancement function
     */
    function enhanceDashboard(weeklyTable) {
        try {
            addChangeColumn(weeklyTable);
            addNewKPIRows(weeklyTable);
            enhanceCustomerNotes();
        } catch (error) {
            console.error('[Dashboard Enhancer] Error enhancing dashboard:', error);
        }
    }

    /**
     * Add % Change column to the weekly table
     */
    function addChangeColumn(table) {
        const headerRow = table.querySelector('thead tr');
        const bodyRows = table.querySelectorAll('tbody tr');

        if (!headerRow) return;

        // Add Change header
        const changeHeader = document.createElement('th');
        changeHeader.textContent = 'Change';
        changeHeader.style.cssText = `
            color: ${THEME.textPrimary};
            font-weight: 600;
            padding: 12px;
            text-align: right;
        `;
        headerRow.appendChild(changeHeader);

        // Add change cells to each row
        bodyRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 3) {
                const thisWeekCell = cells[1];
                const lastWeekCell = cells[2];
                
                const changeCell = document.createElement('td');
                changeCell.style.cssText = `
                    color: ${THEME.textSecondary};
                    padding: 12px;
                    text-align: right;
                    font-weight: 500;
                `;
                
                const change = calculatePercentChange(thisWeekCell.textContent, lastWeekCell.textContent);
                changeCell.innerHTML = formatChangeValue(change);
                
                row.appendChild(changeCell);
            }
        });
    }

    /**
     * Calculate percentage change between two values
     */
    function calculatePercentChange(thisWeek, lastWeek) {
        // Extract numbers from text (handles $, commas, etc.)
        const thisVal = parseFloat(thisWeek.replace(/[$,]/g, '')) || 0;
        const lastVal = parseFloat(lastWeek.replace(/[$,]/g, '')) || 0;
        
        if (lastVal === 0) return null;
        return ((thisVal - lastVal) / lastVal) * 100;
    }

    /**
     * Format change value with arrow and color
     */
    function formatChangeValue(change) {
        if (change === null) return '—';
        
        const isPositive = change >= 0;
        const arrow = isPositive ? '↑' : '↓';
        const color = isPositive ? THEME.success : THEME.danger;
        const formattedChange = Math.abs(change).toFixed(1);
        
        return `<span style="color: ${color};">${arrow}${formattedChange}%</span>`;
    }

    /**
     * Add new KPI rows to the weekly table
     */
    function addNewKPIRows(table) {
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        const newRows = [
            { label: 'ARPU (Avg Revenue Per User)', thisWeek: '—', lastWeek: '—' },
            { label: 'PPV Conversion Rate', thisWeek: '—', lastWeek: '—' },
            { label: 'Avg Ladder Position', thisWeek: '—', lastWeek: '—' },
            { label: 'Active Chatters This Week', thisWeek: '—', lastWeek: '—' },
            { label: 'Churn Count', thisWeek: '—', lastWeek: '—' },
            { label: 'Sexting Sessions Sold', thisWeek: '—', lastWeek: '—' },
            { label: 'Dick Ratings Sold', thisWeek: '—', lastWeek: '—' }
        ];

        newRows.forEach(rowData => {
            const row = createKPIRow(rowData);
            tbody.appendChild(row);
        });

        // Schedule API data loading
        setTimeout(loadKPIData, 1000);
    }

    /**
     * Create a new KPI row element
     */
    function createKPIRow(data) {
        const row = document.createElement('tr');
        row.style.cssText = `border-bottom: 1px solid ${THEME.border};`;
        
        const labelCell = document.createElement('td');
        labelCell.textContent = data.label;
        labelCell.style.cssText = `
            color: ${THEME.textPrimary};
            padding: 12px;
            font-weight: 500;
        `;

        const thisWeekCell = document.createElement('td');
        thisWeekCell.textContent = data.thisWeek;
        thisWeekCell.style.cssText = `
            color: ${THEME.textSecondary};
            padding: 12px;
            text-align: right;
        `;

        const lastWeekCell = document.createElement('td');
        lastWeekCell.textContent = data.lastWeek;
        lastWeekCell.style.cssText = `
            color: ${THEME.textSecondary};
            padding: 12px;
            text-align: right;
        `;

        const changeCell = document.createElement('td');
        changeCell.textContent = '—';
        changeCell.style.cssText = `
            color: ${THEME.textSecondary};
            padding: 12px;
            text-align: right;
        `;

        row.appendChild(labelCell);
        row.appendChild(thisWeekCell);
        row.appendChild(lastWeekCell);
        row.appendChild(changeCell);

        return row;
    }

    /**
     * Load KPI data from API endpoints
     */
    function loadKPIData() {
        // TODO: Implement API calls to fetch real data
        // For now, this is a placeholder that shows where data loading would happen
        
        // Example API calls that would be implemented:
        // fetchARPUData();
        // fetchPPVConversionData();
        // fetchLadderPositionData();
        // fetchChatterData();
        // fetchChurnData();
        // fetchSextingSessionsData();
        // fetchDickRatingsData();
        
        console.log('[Dashboard Enhancer] KPI data loading scheduled (placeholder)');
    }

    /**
     * Enhance the Recent Customer Notes section
     */
    function enhanceCustomerNotes() {
        // Find customer notes section (selector may need adjustment)
        const notesSection = findCustomerNotesSection();
        if (!notesSection) {
            console.log('[Dashboard Enhancer] Customer notes section not found');
            return;
        }

        addCustomerNoteEntries(notesSection);
    }

    /**
     * Find customer notes section
     */
    function findCustomerNotesSection() {
        // Look for section with "Recent Customer Notes" text
        const headings = document.querySelectorAll('h2, h3, h4, .title');
        for (let heading of headings) {
            if (heading.textContent.toLowerCase().includes('customer notes') || 
                heading.textContent.toLowerCase().includes('recent notes')) {
                return heading.parentElement || heading.nextElementSibling;
            }
        }
        return null;
    }

    /**
     * Add new customer note entries
     */
    function addCustomerNoteEntries(notesSection) {
        const newNotes = [
            { type: 'whale-tier', text: 'Fans who just crossed whale tier', count: '—' },
            { type: 'churn-risk', text: 'Fans who are churn risks', count: '—' },
            { type: 'flagged', text: 'Fans with flagged behavior', count: '—' },
            { type: 'promises', text: 'Promises Breyya made that haven\'t been followed up', count: '—' }
        ];

        newNotes.forEach(note => {
            const noteElement = createCustomerNoteElement(note);
            notesSection.appendChild(noteElement);
        });

        // Schedule API data loading for notes
        setTimeout(loadCustomerNotesData, 1500);
    }

    /**
     * Create customer note element
     */
    function createCustomerNoteElement(note) {
        const noteDiv = document.createElement('div');
        noteDiv.className = 'customer-note-enhanced';
        noteDiv.style.cssText = `
            padding: 8px 0;
            border-bottom: 1px solid ${THEME.border};
            display: flex;
            justify-content: space-between;
            align-items: center;
        `;

        const textSpan = document.createElement('span');
        textSpan.textContent = note.text;
        textSpan.style.cssText = `color: ${THEME.textPrimary}; font-size: 14px;`;

        const countSpan = document.createElement('span');
        countSpan.textContent = note.count;
        countSpan.style.cssText = `color: ${THEME.textSecondary}; font-weight: 600;`;

        noteDiv.appendChild(textSpan);
        noteDiv.appendChild(countSpan);

        return noteDiv;
    }

    /**
     * Load customer notes data from API
     */
    function loadCustomerNotesData() {
        // TODO: Implement API calls for customer notes data
        console.log('[Dashboard Enhancer] Customer notes data loading scheduled (placeholder)');
    }

    /**
     * Initialize the dashboard enhancer
     */
    function init() {
        if (!isDashboardPage()) {
            console.log('[Dashboard Enhancer] Not on dashboard page, skipping enhancement');
            return;
        }

        console.log('[Dashboard Enhancer] Initializing dashboard enhancements...');
        waitForDashboard();
    }

    // Start enhancement when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();