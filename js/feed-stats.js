// Feed Stats Enhancement for breyya.com/feed
// Adds like count display and "Seen X ago" timestamp to creator header

(function() {
    'use strict';

    // Only run on /feed/ page
    if (!window.location.pathname.startsWith('/feed/')) {
        return;
    }

    const LIKE_COUNT_FLOOR = 259;
    let statsInitialized = false;

    // Utility functions
    function formatTimeAgo(timestamp) {
        const now = new Date();
        const lastActive = new Date(timestamp);
        const diffMs = now - lastActive;
        const diffMinutes = Math.floor(diffMs / (1000 * 60));
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

        if (diffMinutes < 1) return 'Seen now';
        if (diffMinutes < 60) return `Seen ${diffMinutes}m ago`;
        if (diffHours < 24) return `Seen ${diffHours}h ago`;
        if (diffDays === 1) return 'Seen yesterday';
        if (diffDays < 7) return `Seen ${diffDays}d ago`;
        return 'Seen a while ago';
    }

    function addLikeCount(targetElement, count) {
        // Remove any existing like count
        const existingLike = targetElement.querySelector('.feed-like-count');
        if (existingLike) existingLike.remove();

        const likeElement = document.createElement('span');
        likeElement.className = 'feed-like-count';
        likeElement.style.cssText = `
            color: #9ca3af;
            font-size: 0.875rem;
            margin-left: 0.75rem;
            opacity: 0.8;
            font-weight: 400;
        `;
        likeElement.textContent = `❤️ ${count}`;
        
        targetElement.appendChild(likeElement);
    }

    function addSeenTimestamp(targetElement, timestamp) {
        // Remove any existing seen timestamp
        const existingSeen = targetElement.querySelector('.feed-seen-timestamp');
        if (existingSeen) existingSeen.remove();

        const seenElement = document.createElement('div');
        seenElement.className = 'feed-seen-timestamp';
        seenElement.style.cssText = `
            color: #6b7280;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            opacity: 0.7;
            font-weight: 400;
        `;
        seenElement.textContent = formatTimeAgo(timestamp);
        
        targetElement.appendChild(seenElement);
    }

    async function fetchLikeCount() {
        try {
            const response = await fetch('/api/messages/list.php');
            if (!response.ok) throw new Error('API request failed');
            
            const data = await response.json();
            // Look for like count in the response
            let realLikes = 0;
            if (data && data.likes) {
                realLikes = parseInt(data.likes) || 0;
            } else if (data && Array.isArray(data)) {
                // If it's an array of messages, count likes
                realLikes = data.reduce((sum, msg) => sum + (parseInt(msg.likes) || 0), 0);
            }
            
            return LIKE_COUNT_FLOOR + realLikes;
        } catch (error) {
            console.log('Could not fetch real like count, using floor:', error);
            return LIKE_COUNT_FLOOR;
        }
    }

    async function fetchLastActive() {
        try {
            const response = await fetch('/api/chat/last-active.php');
            if (!response.ok) throw new Error('API request failed');
            
            const data = await response.json();
            return data.last_active || data.timestamp || new Date().toISOString();
        } catch (error) {
            console.log('Could not fetch last active time:', error);
            // Fallback to a recent time
            return new Date(Date.now() - 5 * 60 * 1000).toISOString(); // 5 minutes ago
        }
    }

    function findCreatorHeaderElement() {
        // Look for common creator header patterns
        const selectors = [
            '.creator-header',
            '.feed-creator',
            '.user-info',
            '.profile-header',
            '[class*="creator"]',
            '[class*="profile"]'
        ];

        for (const selector of selectors) {
            const element = document.querySelector(selector);
            if (element) return element;
        }

        // Look for text containing "Breyya" 
        const textNodes = document.evaluate(
            "//text()[contains(., 'Breyya')]",
            document,
            null,
            XPathResult.ANY_TYPE,
            null
        );

        let textNode;
        while (textNode = textNodes.iterateNext()) {
            const parentElement = textNode.parentElement;
            if (parentElement && parentElement.offsetParent !== null) {
                return parentElement;
            }
        }

        return null;
    }

    function findLikeCountContainer() {
        const creatorElement = findCreatorHeaderElement();
        if (!creatorElement) return null;

        // Look for existing like count or good container
        let container = creatorElement.querySelector('[class*="stats"], [class*="meta"], [class*="info"]');
        if (!container) {
            // Use the creator element itself
            container = creatorElement;
        }

        return container;
    }

    function findSeenTimestampContainer() {
        const creatorElement = findCreatorHeaderElement();
        if (!creatorElement) return null;

        // Look for existing status text first
        const existingStatus = creatorElement.querySelector('[class*="status"], [class*="timestamp"]');
        if (existingStatus) {
            return existingStatus.parentElement || existingStatus;
        }

        // Otherwise use creator element
        return creatorElement;
    }

    async function initializeFeedStats() {
        if (statsInitialized) return;

        try {
            // Find target elements
            const likeContainer = findLikeCountContainer();
            const seenContainer = findSeenTimestampContainer();

            if (!likeContainer && !seenContainer) {
                console.log('Could not find creator header elements for feed stats');
                return;
            }

            // Fetch data
            const [likeCount, lastActive] = await Promise.all([
                fetchLikeCount(),
                fetchLastActive()
            ]);

            // Add like count
            if (likeContainer) {
                addLikeCount(likeContainer, likeCount);
            }

            // Add seen timestamp
            if (seenContainer) {
                addSeenTimestamp(seenContainer, lastActive);
            }

            statsInitialized = true;
            console.log('Feed stats initialized successfully');

        } catch (error) {
            console.error('Error initializing feed stats:', error);
        }
    }

    // Wait for React render and DOM ready
    function waitForReactRender() {
        let attempts = 0;
        const maxAttempts = 20; // 10 seconds max wait
        
        const checkInterval = setInterval(() => {
            attempts++;
            
            // Check if we can find creator elements
            if (findCreatorHeaderElement() || attempts >= maxAttempts) {
                clearInterval(checkInterval);
                // Give it a little more time for full render
                setTimeout(initializeFeedStats, 500);
            }
        }, 500);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', waitForReactRender);
    } else {
        waitForReactRender();
    }

    // Also try on window load as backup
    window.addEventListener('load', () => {
        setTimeout(() => {
            if (!statsInitialized) {
                waitForReactRender();
            }
        }, 1000);
    });

})();