/**
 * Breyya Join Page - CCBill Payment Integration
 * Redirects join/subscribe buttons to CCBill FlexForm
 */

(function() {
    'use strict';
    
    // CCBill Configuration
    const CCBILL_CONFIG = {
        FLEX_ID: 'd6c111d7-3565-4d8a-a3d7-211539a585f3',
        SUBSCRIPTION: {
            clientSubacc: '0000',
            initialPrice: '20.00',
            initialPeriod: '30',
            recurringPrice: '20.00',
            recurringPeriod: '30',
            numRebills: '99',
            currencyCode: '840',
            formDigest: '42010502107e545cee59190fdce57394' // MD5("20.003020.003099840XXDzs2W4u9JXgNnXNQ4FyUgk")
        }
    };
    
    // Build subscription URL
    function buildSubscriptionURL() {
        const params = new URLSearchParams({
            clientSubacc: CCBILL_CONFIG.SUBSCRIPTION.clientSubacc,
            initialPrice: CCBILL_CONFIG.SUBSCRIPTION.initialPrice,
            initialPeriod: CCBILL_CONFIG.SUBSCRIPTION.initialPeriod,
            recurringPrice: CCBILL_CONFIG.SUBSCRIPTION.recurringPrice,
            recurringPeriod: CCBILL_CONFIG.SUBSCRIPTION.recurringPeriod,
            numRebills: CCBILL_CONFIG.SUBSCRIPTION.numRebills,
            currencyCode: CCBILL_CONFIG.SUBSCRIPTION.currencyCode,
            formDigest: CCBILL_CONFIG.SUBSCRIPTION.formDigest
        });
        
        return `https://api.ccbill.com/wap-frontflex/flexforms/${CCBILL_CONFIG.FLEX_ID}?${params.toString()}`;
    }
    
    // Initialize when DOM is ready
    function init() {
        // Find subscription/join buttons and replace their click handlers
        const subscribeButtons = document.querySelectorAll('button[type="submit"], .btn-primary, .submitBtn, [class*="submit"]');
        const joinButtons = document.querySelectorAll('a[href="/join"], button[class*="join"]');
        
        const subscriptionURL = buildSubscriptionURL();
        
        // Handle form submission buttons (likely the "CONTINUE TO PAYMENT" button)
        subscribeButtons.forEach(button => {
            if (button.textContent && button.textContent.toUpperCase().includes('PAYMENT')) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Redirect to CCBill instead of form submission
                    window.location.href = subscriptionURL;
                });
            }
        });
        
        // Handle any direct join links
        joinButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                window.location.href = subscriptionURL;
            });
        });
        
        // Also look for form submissions and intercept them
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Redirect to CCBill instead of form processing
                window.location.href = subscriptionURL;
            });
        });
    }
    
    // Initialize when page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();