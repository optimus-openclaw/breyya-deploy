/**
 * Backstage Content Inventory Enhancement
 * Enhances the Content Inventory page at breyya.com/backstage/inventory/
 * Adds new columns and summary cards to provide better revenue and analytics insights
 */

(function() {
    'use strict';
    
    // Only run on the inventory page
    if (!window.location.pathname.includes('/backstage/inventory/')) {
        console.log('Backstage Inventory Enhancement: Not on inventory page, skipping');
        return;
    }
    
    console.log('Backstage Inventory Enhancement: Starting enhancement...');
    
    // Configuration
    const config = {
        maxRetries: 30, // Wait up to 30 seconds for React to render
        retryInterval: 1000, // Check every second
        darkTheme: {
            background: '#1a1a2e',
            cardBg: '#16213e',
            border: '#2a3a5c',
            text: '#ffffff',
            textMuted: '#a0a0a0',
            accent: '#4a90e2'
        }
    };
    
    let retryCount = 0;
    let isEnhanced = false;
    
    // Mock data structure for demonstration (in production, this would come from API)
    const mockAnalytics = {
        totalActiveFans: 0,
        dailyFeedRate: 1, // posts per day
        sets: [
            { id: 1, timesSold: 0, price: 25 },
            { id: 2, timesSold: 0, price: 30 },
            { id: 3, timesSold: 0, price: 20 },
            { id: 4, timesSold: 0, price: 35 },
            { id: 5, timesSold: 0, price: 25 }
        ]
    };
    
    /**
     * Calculate analytics data
     */
    function calculateAnalytics(tableData) {
        const analytics = {
            revenuePerFanCeiling: 0,
            revenuePotentialAllFans: 0,
            avgDepletionPerFan: 0,
            contentRunwayFeed: 0,
            contentRunwayPPV: 0,
            mostPopularSet: '—',
            leastSoldSet: '—'
        };
        
        if (!tableData.length) return analytics;
        
        // Calculate revenue metrics
        let totalRevenue = 0;
        let totalSets = tableData.length;
        let maxSold = 0;
        let minSold = Infinity;
        let mostPopular = null;
        let leastSold = null;
        
        tableData.forEach((row, index) => {
            const price = parseFloat(row.suggestedPrice || 0);
            const timesSold = mockAnalytics.sets[index]?.timesSold || 0;
            const revenue = timesSold * price;
            
            totalRevenue += revenue;
            analytics.revenuePerFanCeiling += price;
            
            if (timesSold > maxSold) {
                maxSold = timesSold;
                mostPopular = row.outfit;
            }
            
            if (timesSold < minSold) {
                minSold = timesSold;
                leastSold = row.outfit;
            }
        });
        
        analytics.revenuePotentialAllFans = analytics.revenuePerFanCeiling * mockAnalytics.totalActiveFans;
        analytics.avgDepletionPerFan = totalSets > 0 ? (totalRevenue / totalSets / mockAnalytics.totalActiveFans * 100).toFixed(1) : 0;
        analytics.contentRunwayFeed = Math.floor(totalSets / mockAnalytics.dailyFeedRate);
        analytics.contentRunwayPPV = Math.floor(totalSets * 0.7); // Assume 70% haven't seen average set
        analytics.mostPopularSet = mostPopular || '—';
        analytics.leastSoldSet = leastSold || '—';
        
        return analytics;
    }
    
    /**
     * Create summary cards HTML
     */
    function createSummaryCards(analytics) {
        return `
            <div class="inventory-summary-cards" style="
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
                padding: 20px;
                background: ${config.darkTheme.background};
                border-radius: 8px;
                border: 1px solid ${config.darkTheme.border};
            ">
                <div class="summary-card" style="
                    background: ${config.darkTheme.cardBg};
                    padding: 20px;
                    border-radius: 6px;
                    border: 1px solid ${config.darkTheme.border};
                    color: ${config.darkTheme.text};
                ">
                    <h3 style="margin: 0 0 10px 0; color: ${config.darkTheme.accent}; font-size: 14px; font-weight: 600;">Revenue Per Fan (Ceiling)</h3>
                    <div style="font-size: 24px; font-weight: bold;">$${analytics.revenuePerFanCeiling.toLocaleString()}</div>
                    <div style="font-size: 12px; color: ${config.darkTheme.textMuted}; margin-top: 5px;">If one fan buys everything</div>
                </div>
                
                <div class="summary-card" style="
                    background: ${config.darkTheme.cardBg};
                    padding: 20px;
                    border-radius: 6px;
                    border: 1px solid ${config.darkTheme.border};
                    color: ${config.darkTheme.text};
                ">
                    <h3 style="margin: 0 0 10px 0; color: ${config.darkTheme.accent}; font-size: 14px; font-weight: 600;">Revenue Potential (All Fans)</h3>
                    <div style="font-size: 24px; font-weight: bold;">$${analytics.revenuePotentialAllFans.toLocaleString()}</div>
                    <div style="font-size: 12px; color: ${config.darkTheme.textMuted}; margin-top: 5px;">Ceiling × ${mockAnalytics.totalActiveFans} active fans</div>
                </div>
                
                <div class="summary-card" style="
                    background: ${config.darkTheme.cardBg};
                    padding: 20px;
                    border-radius: 6px;
                    border: 1px solid ${config.darkTheme.border};
                    color: ${config.darkTheme.text};
                ">
                    <h3 style="margin: 0 0 10px 0; color: ${config.darkTheme.accent}; font-size: 14px; font-weight: 600;">Avg Depletion Per Fan</h3>
                    <div style="font-size: 24px; font-weight: bold;">${analytics.avgDepletionPerFan}%</div>
                    <div style="font-size: 12px; color: ${config.darkTheme.textMuted}; margin-top: 5px;">Of inventory average fan has seen</div>
                </div>
                
                <div class="summary-card" style="
                    background: ${config.darkTheme.cardBg};
                    padding: 20px;
                    border-radius: 6px;
                    border: 1px solid ${config.darkTheme.border};
                    color: ${config.darkTheme.text};
                ">
                    <h3 style="margin: 0 0 10px 0; color: ${config.darkTheme.accent}; font-size: 14px; font-weight: 600;">Content Runway (Feed)</h3>
                    <div style="font-size: 24px; font-weight: bold;">${analytics.contentRunwayFeed} days</div>
                    <div style="font-size: 12px; color: ${config.darkTheme.textMuted}; margin-top: 5px;">Until feed content empty</div>
                </div>
                
                <div class="summary-card" style="
                    background: ${config.darkTheme.cardBg};
                    padding: 20px;
                    border-radius: 6px;
                    border: 1px solid ${config.darkTheme.border};
                    color: ${config.darkTheme.text};
                ">
                    <h3 style="margin: 0 0 10px 0; color: ${config.darkTheme.accent}; font-size: 14px; font-weight: 600;">Content Runway (PPV)</h3>
                    <div style="font-size: 24px; font-weight: bold;">${analytics.contentRunwayPPV} sets</div>
                    <div style="font-size: 12px; color: ${config.darkTheme.textMuted}; margin-top: 5px;">Avg unseen PPVs per fan</div>
                </div>
                
                <div class="summary-card" style="
                    background: ${config.darkTheme.cardBg};
                    padding: 20px;
                    border-radius: 6px;
                    border: 1px solid ${config.darkTheme.border};
                    color: ${config.darkTheme.text};
                ">
                    <h3 style="margin: 0 0 10px 0; color: ${config.darkTheme.accent}; font-size: 14px; font-weight: 600;">Most Popular Set</h3>
                    <div style="font-size: 18px; font-weight: bold; word-break: break-word;">${analytics.mostPopularSet}</div>
                    <div style="font-size: 12px; color: ${config.darkTheme.textMuted}; margin-top: 5px;">Highest times sold</div>
                </div>
                
                <div class="summary-card" style="
                    background: ${config.darkTheme.cardBg};
                    padding: 20px;
                    border-radius: 6px;
                    border: 1px solid ${config.darkTheme.border};
                    color: ${config.darkTheme.text};
                ">
                    <h3 style="margin: 0 0 10px 0; color: ${config.darkTheme.accent}; font-size: 14px; font-weight: 600;">Least Sold Set</h3>
                    <div style="font-size: 18px; font-weight: bold; word-break: break-word;">${analytics.leastSoldSet}</div>
                    <div style="font-size: 12px; color: ${config.darkTheme.textMuted}; margin-top: 5px;">Lowest times sold</div>
                </div>
            </div>
        `;
    }
    
    /**
     * Parse table data from existing table
     */
    function parseTableData(table) {
        const data = [];
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 6) { // Ensure we have enough columns
                data.push({
                    shootDate: cells[0]?.textContent?.trim() || '—',
                    outfit: cells[1]?.textContent?.trim() || '—',
                    tier: cells[2]?.textContent?.trim() || '—',
                    photos: cells[3]?.textContent?.trim() || '—',
                    videos: cells[4]?.textContent?.trim() || '—',
                    suggestedPrice: cells[5]?.textContent?.replace('$', '').trim() || '0',
                    status: cells[6]?.textContent?.trim() || '—'
                });
            }
        });
        
        return data;
    }
    
    /**
     * Add new columns to the table
     */
    function enhanceTable(table, tableData) {
        // Add headers for new columns
        const headerRow = table.querySelector('thead tr');
        if (headerRow && !headerRow.querySelector('.times-sold-header')) {
            const timesSoldHeader = document.createElement('th');
            timesSoldHeader.className = 'times-sold-header';
            timesSoldHeader.textContent = 'Times Sold';
            timesSoldHeader.style.cssText = `
                color: ${config.darkTheme.text};
                border-bottom: 1px solid ${config.darkTheme.border};
                padding: 12px;
                text-align: left;
                font-weight: 600;
            `;
            
            const totalRevenueHeader = document.createElement('th');
            totalRevenueHeader.className = 'total-revenue-header';
            totalRevenueHeader.textContent = 'Total Revenue';
            totalRevenueHeader.style.cssText = timesSoldHeader.style.cssText;
            
            const fansRemainingHeader = document.createElement('th');
            fansRemainingHeader.className = 'fans-remaining-header';
            fansRemainingHeader.textContent = 'Fans Remaining';
            fansRemainingHeader.style.cssText = timesSoldHeader.style.cssText;
            
            headerRow.appendChild(timesSoldHeader);
            headerRow.appendChild(totalRevenueHeader);
            headerRow.appendChild(fansRemainingHeader);
        }
        
        // Add data cells for new columns
        const bodyRows = table.querySelectorAll('tbody tr');
        bodyRows.forEach((row, index) => {
            if (!row.querySelector('.times-sold-cell')) {
                const mockData = mockAnalytics.sets[index] || { timesSold: 0, price: 0 };
                const price = parseFloat(tableData[index]?.suggestedPrice || 0);
                const timesSold = mockData.timesSold;
                const totalRevenue = timesSold * price;
                const fansRemaining = mockAnalytics.totalActiveFans - timesSold;
                
                const timesSoldCell = document.createElement('td');
                timesSoldCell.className = 'times-sold-cell';
                timesSoldCell.textContent = timesSold > 0 ? timesSold.toString() : '—';
                timesSoldCell.style.cssText = `
                    color: ${config.darkTheme.text};
                    border-bottom: 1px solid ${config.darkTheme.border};
                    padding: 12px;
                `;
                
                const totalRevenueCell = document.createElement('td');
                totalRevenueCell.className = 'total-revenue-cell';
                totalRevenueCell.textContent = totalRevenue > 0 ? `$${totalRevenue.toLocaleString()}` : '—';
                totalRevenueCell.style.cssText = timesSoldCell.style.cssText;
                
                const fansRemainingCell = document.createElement('td');
                fansRemainingCell.className = 'fans-remaining-cell';
                fansRemainingCell.textContent = fansRemaining > 0 ? fansRemaining.toString() : '—';
                fansRemainingCell.style.cssText = timesSoldCell.style.cssText;
                
                row.appendChild(timesSoldCell);
                row.appendChild(totalRevenueCell);
                row.appendChild(fansRemainingCell);
            }
        });
    }
    
    /**
     * Main enhancement function
     */
    function enhanceInventoryPage() {
        console.log(`Backstage Inventory Enhancement: Attempt ${retryCount + 1}/${config.maxRetries}`);
        
        // Look for the inventory table
        const table = document.querySelector('table');
        const tableContainer = table?.closest('div');
        
        if (!table || !tableContainer) {
            retryCount++;
            if (retryCount < config.maxRetries) {
                console.log('Backstage Inventory Enhancement: Table not found, retrying...');
                setTimeout(enhanceInventoryPage, config.retryInterval);
                return;
            } else {
                console.log('Backstage Inventory Enhancement: Table not found after max retries');
                return;
            }
        }
        
        // Check if already enhanced
        if (isEnhanced || document.querySelector('.inventory-summary-cards')) {
            console.log('Backstage Inventory Enhancement: Already enhanced');
            return;
        }
        
        console.log('Backstage Inventory Enhancement: Found table, enhancing...');
        
        try {
            // Parse existing table data
            const tableData = parseTableData(table);
            console.log('Backstage Inventory Enhancement: Parsed table data:', tableData.length, 'rows');
            
            // Calculate analytics
            const analytics = calculateAnalytics(tableData);
            console.log('Backstage Inventory Enhancement: Calculated analytics:', analytics);
            
            // Create and insert summary cards
            const summaryCardsHTML = createSummaryCards(analytics);
            const summaryContainer = document.createElement('div');
            summaryContainer.innerHTML = summaryCardsHTML;
            
            // Insert before the table container
            tableContainer.parentNode.insertBefore(summaryContainer.firstElementChild, tableContainer);
            
            // Enhance the table with new columns
            enhanceTable(table, tableData);
            
            isEnhanced = true;
            console.log('Backstage Inventory Enhancement: Successfully enhanced inventory page');
            
        } catch (error) {
            console.error('Backstage Inventory Enhancement: Error during enhancement:', error);
        }
    }
    
    /**
     * Initialize the enhancement
     */
    function init() {
        console.log('Backstage Inventory Enhancement: Initializing...');
        
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', enhanceInventoryPage);
        } else {
            enhanceInventoryPage();
        }
        
        // Also listen for navigation changes (in case of SPA routing)
        const originalPushState = history.pushState;
        history.pushState = function() {
            originalPushState.apply(history, arguments);
            setTimeout(() => {
                if (window.location.pathname.includes('/backstage/inventory/') && !isEnhanced) {
                    retryCount = 0;
                    enhanceInventoryPage();
                }
            }, 1000);
        };
    }
    
    // Start the enhancement
    init();
    
})();