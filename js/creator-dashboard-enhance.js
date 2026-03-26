// Creator Dashboard Enhancement for breyya.com/dashboard/
// Adds 6 new summary cards and color-coded activity feed

(function() {
    'use strict';
    
    // Only run on the dashboard page, not backstage
    if (!window.location.pathname.includes('/dashboard/') || 
        window.location.pathname.includes('/backstage/dashboard/')) {
        return;
    }
    
    // Event type configurations for activity feed
    const EVENT_TYPES = {
        'message_sent': { color: '#6b7280', icon: '💬', label: 'sent message' },
        'breyya_replied': { color: '#ec4899', icon: '🌸', label: 'Breyya replied' },
        'tip_received': { color: '#10b981', icon: '💰', label: 'tipped' },
        'ppv_purchased': { color: '#f59e0b', icon: '🔓', label: 'unlocked' },
        'new_subscriber': { color: '#3b82f6', icon: '⭐', label: 'subscribed' },
        'image_uploaded': { color: '#8b5cf6', icon: '📸', label: 'uploaded image' },
        'fan_flagged': { color: '#ef4444', icon: '⚠️', label: 'flagged' },
        'rating_delivered': { color: '#f97316', icon: '🔥', label: 'Rating delivered' },
        'churn_alert': { color: '#eab308', icon: '⏰', label: 'silent' }
    };
    
    // Wait for React to render
    function waitForReactRender(callback, maxAttempts = 50) {
        let attempts = 0;
        
        function check() {
            attempts++;
            
            // Look for existing summary cards container
            const existingCards = document.querySelector('.grid') || 
                                document.querySelector('[class*="grid"]') ||
                                document.querySelector('.summary-cards') ||
                                document.querySelector('[class*="card"]');
            
            if (existingCards || attempts >= maxAttempts) {
                callback(existingCards);
            } else {
                setTimeout(check, 200);
            }
        }
        
        check();
    }
    
    // Create new summary card element
    function createSummaryCard(title, value, subtitle = '') {
        const card = document.createElement('div');
        card.className = 'bg-gray-800 rounded-lg p-4 border border-gray-700';
        card.innerHTML = `
            <div class="flex flex-col">
                <h3 class="text-sm font-medium text-gray-400 mb-1">${title}</h3>
                <div class="text-2xl font-bold text-white mb-1">${value}</div>
                ${subtitle ? `<div class="text-xs text-gray-500">${subtitle}</div>` : ''}
            </div>
        `;
        return card;
    }
    
    // Add new summary cards
    function addSummaryCards(existingContainer) {
        if (!existingContainer) return;
        
        // Find or create cards container
        let cardsContainer = existingContainer;
        
        // If we found a grid, use it; otherwise create one
        if (!cardsContainer.classList.contains('grid') && !cardsContainer.className.includes('grid')) {
            const gridContainer = document.createElement('div');
            gridContainer.className = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6';
            cardsContainer.parentNode.insertBefore(gridContainer, cardsContainer.nextSibling);
            cardsContainer = gridContainer;
        }
        
        // Mock data - in real implementation, this would come from API
        const newCards = [
            { title: 'Active Chatters Today', value: '—', subtitle: 'fans who sent 1+ message' },
            { title: 'Messages Today', value: '—', subtitle: 'total processed' },
            { title: 'PPV Sales Today', value: '—', subtitle: '0 unlocks • $0' },
            { title: 'Tips Today', value: '—', subtitle: '0 tips • $0' },
            { title: 'Whale Count', value: '—', subtitle: 'fans at tier 70+' },
            { title: 'Ratings Today', value: '—', subtitle: 'dick ratings delivered' }
        ];
        
        // Add each new card
        newCards.forEach(cardData => {
            const card = createSummaryCard(cardData.title, cardData.value, cardData.subtitle);
            cardsContainer.appendChild(card);
        });
        
        console.log('✅ Added 6 new summary cards to dashboard');
    }
    
    // Enhance activity feed with color coding
    function enhanceActivityFeed() {
        // Look for activity feed container
        const activitySelectors = [
            '.activity-feed',
            '[class*="activity"]',
            '.live-feed',
            '[class*="feed"]',
            '.recent-activity',
            '.events-list'
        ];
        
        let activityContainer = null;
        for (const selector of activitySelectors) {
            activityContainer = document.querySelector(selector);
            if (activityContainer) break;
        }
        
        // If no specific container found, look for lists of items that might be activity
        if (!activityContainer) {
            const listContainers = document.querySelectorAll('ul, div[class*="list"], div[class*="item"]');
            for (const container of listContainers) {
                if (container.textContent.includes('sent message') || 
                    container.textContent.includes('ago') ||
                    container.textContent.includes('subscribed') ||
                    container.textContent.includes('tipped')) {
                    activityContainer = container;
                    break;
                }
            }
        }
        
        if (!activityContainer) {
            console.log('⚠️ Activity feed container not found');
            return;
        }
        
        // Enhance existing activity items
        const activityItems = activityContainer.querySelectorAll('li, div[class*="item"], .activity-item');
        
        activityItems.forEach(item => {
            const text = item.textContent.toLowerCase();
            let eventType = 'message_sent'; // default
            
            // Determine event type based on content
            if (text.includes('breyya replied')) eventType = 'breyya_replied';
            else if (text.includes('tipped')) eventType = 'tip_received';
            else if (text.includes('unlocked') || text.includes('purchased')) eventType = 'ppv_purchased';
            else if (text.includes('subscribed')) eventType = 'new_subscriber';
            else if (text.includes('uploaded')) eventType = 'image_uploaded';
            else if (text.includes('flagged')) eventType = 'fan_flagged';
            else if (text.includes('rating delivered')) eventType = 'rating_delivered';
            else if (text.includes('silent') || text.includes('churn')) eventType = 'churn_alert';
            
            const config = EVENT_TYPES[eventType];
            
            // Apply styling
            item.style.borderLeft = `4px solid ${config.color}`;
            item.style.paddingLeft = '12px';
            item.style.marginBottom = '8px';
            item.style.backgroundColor = 'rgba(75, 85, 99, 0.1)';
            item.style.borderRadius = '6px';
            item.style.padding = '8px 12px';
            
            // Add icon if not already present
            if (!item.textContent.includes(config.icon)) {
                const iconSpan = document.createElement('span');
                iconSpan.textContent = config.icon + ' ';
                iconSpan.style.marginRight = '8px';
                item.insertBefore(iconSpan, item.firstChild);
            }
        });
        
        console.log(`✅ Enhanced ${activityItems.length} activity feed items`);
    }
    
    // Observer to handle dynamically loaded content
    function observeForChanges() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.addedNodes.length > 0) {
                    // Re-enhance any new activity items
                    setTimeout(enhanceActivityFeed, 100);
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        return observer;
    }
    
    // CSS styles for dark theme compatibility
    function addStyles() {
        const styles = `
            <style id="creator-dashboard-enhance-styles">
                .creator-dashboard-enhanced .summary-card {
                    background-color: #1f2937;
                    border: 1px solid #374151;
                    border-radius: 8px;
                    padding: 16px;
                    transition: all 0.2s ease;
                }
                
                .creator-dashboard-enhanced .summary-card:hover {
                    background-color: #253241;
                    border-color: #4b5563;
                }
                
                .creator-dashboard-enhanced .activity-item {
                    background-color: rgba(75, 85, 99, 0.1) !important;
                    border-radius: 6px !important;
                    margin-bottom: 8px !important;
                    transition: all 0.2s ease;
                }
                
                .creator-dashboard-enhanced .activity-item:hover {
                    background-color: rgba(75, 85, 99, 0.2) !important;
                }
                
                @media (max-width: 768px) {
                    .creator-dashboard-enhanced .grid {
                        grid-template-columns: 1fr !important;
                    }
                }
                
                @media (min-width: 769px) and (max-width: 1024px) {
                    .creator-dashboard-enhanced .grid {
                        grid-template-columns: repeat(2, 1fr) !important;
                    }
                }
                
                @media (min-width: 1025px) {
                    .creator-dashboard-enhanced .grid {
                        grid-template-columns: repeat(4, 1fr) !important;
                    }
                }
            </style>
        `;
        document.head.insertAdjacentHTML('beforeend', styles);
    }
    
    // Main initialization function
    function initialize() {
        console.log('🚀 Initializing Creator Dashboard Enhancement');
        
        // Add CSS styles
        addStyles();
        
        // Add class to body for scoped styling
        document.body.classList.add('creator-dashboard-enhanced');
        
        // Wait for React render, then add features
        waitForReactRender((existingContainer) => {
            if (existingContainer) {
                addSummaryCards(existingContainer);
                enhanceActivityFeed();
                
                // Set up observer for dynamic content
                observeForChanges();
                
                console.log('✅ Creator Dashboard Enhancement initialized successfully');
            } else {
                console.log('⚠️ Could not find dashboard container, enhancement may not work properly');
            }
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
    
    // Also initialize if we're loaded after React has already rendered
    setTimeout(initialize, 1000);
    
})();