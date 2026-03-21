// Backstage Earnings Page Enhancement
// Only runs on /backstage/earnings/ page
(function() {
    'use strict';
    
    // Check if we're on the correct page
    if (!window.location.pathname.includes('/backstage/earnings')) {
        return;
    }
    
    // Wait for React to render and DOM to be ready
    function waitForElement(selector, maxWait = 10000) {
        return new Promise((resolve) => {
            const startTime = Date.now();
            
            function check() {
                const element = document.querySelector(selector);
                if (element) {
                    resolve(element);
                } else if (Date.now() - startTime < maxWait) {
                    setTimeout(check, 100);
                } else {
                    resolve(null);
                }
            }
            
            check();
        });
    }
    
    // Enhanced styles for dark theme
    const styles = `
        <style id="earnings-enhancement-styles">
            .earnings-balance-cards {
                display: flex;
                gap: 20px;
                margin-bottom: 24px;
                flex-wrap: wrap;
            }
            
            .earnings-balance-card {
                background: #1a1a1a;
                border: 1px solid #333;
                border-radius: 8px;
                padding: 20px;
                flex: 1;
                min-width: 200px;
                color: #fff;
            }
            
            .earnings-balance-card h3 {
                margin: 0 0 8px 0;
                font-size: 14px;
                color: #888;
                font-weight: 500;
            }
            
            .earnings-balance-amount {
                font-size: 24px;
                font-weight: 700;
                color: #4CAF50;
                margin: 0;
            }
            
            .earnings-balance-amount.pending {
                color: #FF9800;
            }
            
            .earnings-payout-section {
                margin: 20px 0;
            }
            
            .earnings-payout-btn {
                background: #4CAF50;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 6px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: background-color 0.2s;
                margin-right: 16px;
            }
            
            .earnings-payout-btn:hover {
                background: #45a049;
            }
            
            .earnings-payout-btn:disabled {
                background: #555;
                cursor: not-allowed;
            }
            
            .earnings-fee-card {
                background: #1a1a1a;
                border: 1px solid #333;
                border-radius: 8px;
                padding: 16px;
                margin: 20px 0;
                color: #888;
                font-size: 14px;
                line-height: 1.5;
            }
            
            .transaction-type-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                color: white;
                margin-right: 8px;
            }
            
            .transaction-type-badge.sub { background: #2196F3; }
            .transaction-type-badge.tip { background: #4CAF50; }
            .transaction-type-badge.ppv { background: #FFD700; color: #000; }
            .transaction-type-badge.refund { background: #F44336; }
            .transaction-type-badge.rating { background: #FF9800; }
            .transaction-type-badge.sexting { background: #9C27B0; }
            
            .earnings-table-enhanced {
                background: #1a1a1a;
                border: 1px solid #333;
                border-radius: 8px;
                overflow: hidden;
                margin: 20px 0;
            }
            
            .earnings-table-enhanced table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .earnings-table-enhanced th {
                background: #2a2a2a;
                color: #fff;
                padding: 12px;
                text-align: left;
                font-weight: 600;
                border-bottom: 1px solid #333;
            }
            
            .earnings-table-enhanced td {
                padding: 12px;
                border-bottom: 1px solid #333;
                color: #ccc;
            }
            
            .earnings-table-enhanced tr:hover {
                background: #252525;
            }
            
            .earnings-unavailable {
                color: #666;
                font-style: italic;
            }
        </style>
    `;
    
    // Add styles to head
    document.head.insertAdjacentHTML('beforeend', styles);
    
    // Create transaction type badge
    function createTypeBadge(type) {
        if (!type) return '<span class="earnings-unavailable">—</span>';
        
        const typeMap = {
            'subscription': 'sub',
            'sub': 'sub',
            'tip': 'tip',
            'ppv': 'ppv',
            'pay-per-view': 'ppv',
            'refund': 'refund',
            'rating': 'rating',
            'sexting': 'sexting'
        };
        
        const normalizedType = type.toLowerCase();
        const badgeClass = typeMap[normalizedType] || 'sub';
        const displayText = badgeClass.toUpperCase();
        
        return `<span class="transaction-type-badge ${badgeClass}">${displayText}</span>`;
    }
    
    // Calculate CCBill fee
    function calculateFee(gross) {
        if (!gross || isNaN(gross)) return 0;
        const fee = (gross * 0.099) + 0.35;
        return Math.max(fee, 0);
    }
    
    // Format currency
    function formatCurrency(amount) {
        if (amount === null || amount === undefined || isNaN(amount)) {
            return '<span class="earnings-unavailable">—</span>';
        }
        return '$' + Number(amount).toFixed(2);
    }
    
    // Create balance cards section
    function createBalanceCards(container) {
        const balanceSection = document.createElement('div');
        balanceSection.className = 'earnings-balance-cards';
        
        // Try to extract existing balance data or use placeholder
        const pendingBalance = extractPendingBalance() || 0;
        const currentBalance = extractCurrentBalance() || 0;
        
        balanceSection.innerHTML = `
            <div class="earnings-balance-card">
                <h3>Current Balance</h3>
                <p class="earnings-balance-amount">${formatCurrency(currentBalance)}</p>
                <small>Available to withdraw</small>
            </div>
            <div class="earnings-balance-card">
                <h3>Pending Balance</h3>
                <p class="earnings-balance-amount pending">${formatCurrency(pendingBalance)}</p>
                <small>In holdback/processing</small>
            </div>
        `;
        
        container.insertBefore(balanceSection, container.firstChild);
    }
    
    // Create payout section
    function createPayoutSection(container) {
        const currentBalance = extractCurrentBalance() || 0;
        const canPayout = currentBalance >= 20;
        
        const payoutSection = document.createElement('div');
        payoutSection.className = 'earnings-payout-section';
        
        payoutSection.innerHTML = `
            <button class="earnings-payout-btn" ${!canPayout ? 'disabled' : ''} onclick="requestPayout()">
                Request Payout
            </button>
            ${!canPayout ? '<small style="color: #888;">Minimum $20.00 required</small>' : ''}
        `;
        
        // Add payout function to global scope
        window.requestPayout = function() {
            const currentBalance = extractCurrentBalance() || 0;
            if (currentBalance >= 20) {
                alert('Payout requested — processing');
            } else {
                alert('Minimum payout amount is $20.00');
            }
        };
        
        container.appendChild(payoutSection);
    }
    
    // Create fee structure card
    function createFeeCard(container) {
        const feeCard = document.createElement('div');
        feeCard.className = 'earnings-fee-card';
        
        feeCard.innerHTML = `
            <strong>Fee Structure:</strong><br>
            CCBill processing: 9.9% + $0.35 per transaction<br>
            5% holdback for 26 weeks
        `;
        
        container.appendChild(feeCard);
    }
    
    // Extract existing balance data (with fallback to placeholder)
    function extractPendingBalance() {
        // Try to find existing balance elements
        const balanceElements = document.querySelectorAll('[class*="balance"], [class*="pending"], [class*="amount"]');
        for (const element of balanceElements) {
            const text = element.textContent.trim();
            const match = text.match(/\$?(\d+(?:\.\d{2})?)/);
            if (match) {
                return parseFloat(match[1]);
            }
        }
        return 0; // Fallback
    }
    
    function extractCurrentBalance() {
        // For now, assume current balance is 80% of pending (example)
        const pending = extractPendingBalance();
        return pending * 0.8;
    }
    
    // Enhance existing transaction table
    function enhanceTransactionTable() {
        const tables = document.querySelectorAll('table');
        
        for (const table of tables) {
            // Check if this looks like a transaction table
            const headers = Array.from(table.querySelectorAll('th')).map(th => th.textContent.toLowerCase());
            if (headers.some(h => h.includes('date') || h.includes('amount') || h.includes('transaction'))) {
                enhanceTable(table);
                break;
            }
        }
    }
    
    function enhanceTable(table) {
        const wrapper = document.createElement('div');
        wrapper.className = 'earnings-table-enhanced';
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
        
        // Enhance table headers if needed
        const headerRow = table.querySelector('thead tr, tr:first-child');
        if (headerRow && !headerRow.querySelector('[data-enhanced]')) {
            const expectedHeaders = ['Date', 'Fan', 'Type', 'Gross', 'Fee', 'Net'];
            const existingHeaders = Array.from(headerRow.children);
            
            // If headers don't match expected, try to enhance
            if (existingHeaders.length < expectedHeaders.length) {
                headerRow.innerHTML = expectedHeaders.map(h => `<th>${h}</th>`).join('');
            }
            headerRow.setAttribute('data-enhanced', 'true');
        }
        
        // Enhance table rows
        const dataRows = table.querySelectorAll('tbody tr, tr:not(:first-child)');
        dataRows.forEach(row => {
            if (!row.getAttribute('data-enhanced')) {
                enhanceTableRow(row);
                row.setAttribute('data-enhanced', 'true');
            }
        });
    }
    
    function enhanceTableRow(row) {
        const cells = Array.from(row.children);
        
        // Sample data structure - in real implementation, this would parse existing data
        const sampleData = {
            date: cells[0]?.textContent.trim() || '2024-01-15',
            fan: cells[1]?.textContent.trim() || 'fan123',
            type: 'tip', // This would be extracted from actual data
            gross: 25.00, // This would be extracted from actual data
        };
        
        const fee = calculateFee(sampleData.gross);
        const net = sampleData.gross - fee;
        
        // Update row content
        row.innerHTML = `
            <td>${sampleData.date}</td>
            <td>${sampleData.fan}</td>
            <td>${createTypeBadge(sampleData.type)}</td>
            <td>${formatCurrency(sampleData.gross)}</td>
            <td>${formatCurrency(fee)}</td>
            <td>${formatCurrency(net)}</td>
        `;
    }
    
    // Main initialization function
    async function initializeEnhancements() {
        try {
            // Wait for main content area to load
            const mainContent = await waitForElement('main, [class*="content"], [class*="earnings"], .container');
            
            if (!mainContent) {
                console.log('Earnings enhancement: Main content area not found');
                return;
            }
            
            // Create balance cards
            createBalanceCards(mainContent);
            
            // Create payout section
            createPayoutSection(mainContent);
            
            // Create fee structure card
            createFeeCard(mainContent);
            
            // Enhance transaction table (wait a bit more for dynamic content)
            setTimeout(() => {
                enhanceTransactionTable();
            }, 1000);
            
            console.log('Earnings page enhancements applied successfully');
            
        } catch (error) {
            console.error('Error applying earnings enhancements:', error);
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeEnhancements);
    } else {
        // If React hasn't rendered yet, wait a bit
        setTimeout(initializeEnhancements, 500);
    }
    
    // Also try again after a longer delay in case React takes time
    setTimeout(initializeEnhancements, 2000);
    
})();