/**
 * tip-button.js
 * Persistent tip buttons for chat bar and feed page
 * Replaces per-message tip icons with cleaner persistent UI
 */

(function() {
    'use strict';

    let tipButtonsInitialized = false;

    // Main initialization
    function initTipButtons() {
        if (tipButtonsInitialized) return;
        
        // Add styles
        addTipButtonStyles();
        
        // Initialize based on page
        if (window.location.pathname.includes('/chat')) {
            initChatTipButton();
        } else if (window.location.pathname.includes('/feed')) {
            initFeedTipButton();
        }
        
        tipButtonsInitialized = true;
    }

    // Initialize chat page persistent tip button
    function initChatTipButton() {
        // Wait for chat interface to load
        const checkChatInterface = () => {
            const chatPage = document.querySelector('[class*="chat-module"]');
            if (!chatPage) {
                setTimeout(checkChatInterface, 100);
                return;
            }
            
            // Look for the existing input/send area
            addChatTipButton(chatPage);
        };
        
        checkChatInterface();
    }

    // Add tip button to chat input area
    function addChatTipButton(chatPage) {
        // Create a floating tip button that stays at the bottom
        const existingTipBtn = document.querySelector('#persistent-tip-button');
        if (existingTipBtn) return; // Already exists
        
        const tipButton = document.createElement('button');
        tipButton.id = 'persistent-tip-button';
        tipButton.className = 'persistent-tip-btn';
        tipButton.innerHTML = '💰';
        tipButton.title = 'Send a tip';
        
        tipButton.addEventListener('click', () => showTipPopup());
        
        // Add to the page
        chatPage.appendChild(tipButton);
    }

    // Initialize feed page tip button
    function initFeedTipButton() {
        // Wait for creator header to load
        const checkCreatorHeader = () => {
            const creatorHeader = document.querySelector('[class*="creatorHeader"], [class*="creatorInfo"]');
            if (!creatorHeader) {
                setTimeout(checkCreatorHeader, 100);
                return;
            }
            
            addFeedTipButton(creatorHeader);
        };
        
        checkCreatorHeader();
    }

    // Add tip button to feed creator header
    function addFeedTipButton(creatorArea) {
        const existingTipBtn = document.querySelector('#feed-tip-button');
        if (existingTipBtn) return; // Already exists
        
        const tipButton = document.createElement('button');
        tipButton.id = 'feed-tip-button';
        tipButton.className = 'feed-tip-btn';
        tipButton.innerHTML = '💰 Tip';
        tipButton.title = 'Send a tip to Breyya';
        
        tipButton.addEventListener('click', () => showTipPopup());
        
        // Add to creator info area
        creatorArea.appendChild(tipButton);
    }

    // Show tip popup with preset amounts
    function showTipPopup() {
        const existingPopup = document.querySelector('.tip-button-overlay');
        if (existingPopup) return; // Already open
        
        const overlay = document.createElement('div');
        overlay.className = 'tip-button-overlay';
        overlay.innerHTML = `
            <div class="tip-button-popup">
                <div class="tip-button-header">
                    <h3>Send a tip to Breyya 💕</h3>
                    <button class="tip-button-close" type="button">×</button>
                </div>
                
                <div class="tip-button-content">
                    <div class="tip-preset-amounts">
                        <button class="tip-preset-btn" data-amount="5">$5</button>
                        <button class="tip-preset-btn" data-amount="10">$10</button>
                        <button class="tip-preset-btn" data-amount="20">$20</button>
                        <button class="tip-preset-btn" data-amount="50">$50</button>
                    </div>
                    
                    <div class="tip-custom-amount">
                        <label>Custom amount:</label>
                        <div class="tip-custom-input">
                            <span>$</span>
                            <input type="number" id="custom-tip-amount" placeholder="0.00" min="1" max="500" step="0.01">
                        </div>
                    </div>
                    
                    <button class="tip-send-btn" type="button">Send Tip</button>
                    
                    <div class="tip-error-msg" style="display: none;"></div>
                </div>
                
                <div class="tip-processing" style="display: none;">
                    <div class="tip-spinner"></div>
                    <p>Processing payment...</p>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        
        // Event listeners
        setupPopupEventListeners(overlay);
        
        // Focus first preset button
        setTimeout(() => {
            const firstPreset = overlay.querySelector('.tip-preset-btn');
            if (firstPreset) firstPreset.focus();
        }, 100);
    }

    // Setup event listeners for popup
    function setupPopupEventListeners(overlay) {
        const popup = overlay.querySelector('.tip-button-popup');
        const closeBtn = overlay.querySelector('.tip-button-close');
        const presetBtns = overlay.querySelectorAll('.tip-preset-btn');
        const customInput = overlay.querySelector('#custom-tip-amount');
        const sendBtn = overlay.querySelector('.tip-send-btn');
        const errorMsg = overlay.querySelector('.tip-error-msg');
        
        let selectedAmount = 0;
        
        // Close handlers
        const closePopup = () => overlay.remove();
        
        closeBtn.onclick = closePopup;
        overlay.onclick = (e) => {
            if (e.target === overlay) closePopup();
        };
        
        // Preset amount selection
        presetBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Clear previous selection
                presetBtns.forEach(b => b.classList.remove('selected'));
                customInput.value = '';
                errorMsg.style.display = 'none';
                
                // Select this button
                btn.classList.add('selected');
                selectedAmount = parseFloat(btn.dataset.amount);
                updateSendButton();
            });
        });
        
        // Custom amount input
        customInput.addEventListener('input', () => {
            // Clear preset selection
            presetBtns.forEach(b => b.classList.remove('selected'));
            errorMsg.style.display = 'none';
            
            const value = parseFloat(customInput.value);
            if (value >= 1 && value <= 500) {
                selectedAmount = value;
                customInput.classList.remove('error');
            } else {
                selectedAmount = 0;
                if (customInput.value) {
                    customInput.classList.add('error');
                }
            }
            updateSendButton();
        });
        
        // Send button
        sendBtn.addEventListener('click', () => {
            if (selectedAmount >= 1) {
                processTip(selectedAmount, overlay);
            } else {
                showError(errorMsg, 'Please select or enter an amount between $1 and $500');
            }
        });
        
        function updateSendButton() {
            if (selectedAmount >= 1) {
                sendBtn.disabled = false;
                sendBtn.textContent = `Send $${selectedAmount.toFixed(2)}`;
            } else {
                sendBtn.disabled = true;
                sendBtn.textContent = 'Send Tip';
            }
        }
    }

    // Process the tip payment
    async function processTip(amount, overlay) {
        const processing = overlay.querySelector('.tip-processing');
        const sendBtn = overlay.querySelector('.tip-send-btn');
        const errorMsg = overlay.querySelector('.tip-error-msg');
        
        try {
            // Get user ID from JWT token
            const userId = getCurrentUserId();
            if (!userId) {
                showError(errorMsg, 'Please log in to send a tip');
                return;
            }
            
            // Show processing state
            processing.style.display = 'block';
            sendBtn.disabled = true;
            sendBtn.textContent = 'Processing...';
            errorMsg.style.display = 'none';
            
            // Call the tip API
            const response = await fetch('/api/payments/cbpt-charge.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    fan_user_id: userId,
                    amount: amount,
                    description: 'Tip'
                })
            });

            const data = await response.json();

            if (data.success) {
                // Success! Show success and add chat notification
                showTipSuccess(overlay, amount);
                addChatTipNotification(amount);
            } else {
                // Payment failed - check if we need to redirect to FlexForm
                if (data.error && data.error.includes('No stored payment method')) {
                    redirectToFlexForm(amount);
                } else {
                    showError(errorMsg, data.error || 'Payment failed');
                    processing.style.display = 'none';
                    sendBtn.disabled = false;
                    sendBtn.textContent = `Send $${amount.toFixed(2)}`;
                }
            }

        } catch (error) {
            console.error('Tip processing error:', error);
            showError(errorMsg, 'Network error. Please try again.');
            processing.style.display = 'none';
            sendBtn.disabled = false;
            sendBtn.textContent = `Send $${amount.toFixed(2)}`;
        }
    }

    // Show success message
    function showTipSuccess(overlay, amount) {
        const popup = overlay.querySelector('.tip-button-popup');
        popup.innerHTML = `
            <div class="tip-success-content">
                <div class="tip-success-icon">💕</div>
                <h3>Tip sent!</h3>
                <p>Your $${amount.toFixed(2)} tip was sent to Breyya.</p>
                <button class="tip-success-done" type="button">Done</button>
            </div>
        `;

        const doneBtn = popup.querySelector('.tip-success-done');
        doneBtn.onclick = () => overlay.remove();
        doneBtn.focus();

        // Auto-close after 3 seconds
        setTimeout(() => overlay.remove(), 3000);
    }

    // Add tip notification to chat
    function addChatTipNotification(amount) {
        if (!window.location.pathname.includes('/chat')) return;
        
        // Find the chat messages container
        const messagesContainer = document.querySelector('[class*="messages"]');
        if (!messagesContainer) return;
        
        const notification = document.createElement('div');
        notification.className = 'chat-tip-notification';
        notification.innerHTML = `💰 You tipped $${amount.toFixed(2)}`;
        
        messagesContainer.appendChild(notification);
        
        // Scroll to show the notification
        notification.scrollIntoView({ behavior: 'smooth', block: 'end' });
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    // Redirect to FlexForm for first-time users
    function redirectToFlexForm(amount) {
        const formattedAmount = amount.toFixed(2);
        
        // Calculate FlexForm digest for tips (sub-account 0001)
        const initialPeriod = '3';
        const currencyCode = '840';
        const salt = 'XXDzs2W4u9XtgNnXNQ4FyUgk';
        const digestString = formattedAmount + initialPeriod + currencyCode + salt;
        const digest = simpleMD5(digestString);
        
        const flexFormUrl = `https://api.ccbill.com/wap-frontflex/flexforms/d6c111d7-3565-4d8a-a3d7-211539a585f3?clientSubacc=0001&initialPrice=${formattedAmount}&initialPeriod=${initialPeriod}&recurringPrice=0.00&recurringPeriod=0&numRebills=0&currencyCode=${currencyCode}&formDigest=${digest}`;
        
        window.location.href = flexFormUrl;
    }

    // Show error message
    function showError(errorElement, message) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }

    // Get current user ID from JWT token
    function getCurrentUserId() {
        try {
            const token = getCookie('breyya_token') || 
                         localStorage.getItem('breyya_token') || 
                         localStorage.getItem('token') || 
                         localStorage.getItem('jwt_token') || '';
            
            if (!token) return null;
            
            // Decode JWT payload (basic decode, no verification)
            const parts = token.split('.');
            if (parts.length !== 3) return null;
            
            const payload = JSON.parse(atob(parts[1]));
            return payload.sub || null;
        } catch(e) {
            return null;
        }
    }

    // Helper to get cookie value
    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for(let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    // Simple MD5 implementation (reusing from tip-confirm.js)
    function simpleMD5(str) {
        function md5cycle(x, k) {
            var a = x[0], b = x[1], c = x[2], d = x[3];
            a = ff(a, b, c, d, k[0], 7, -680876936);
            d = ff(d, a, b, c, k[1], 12, -389564586);
            c = ff(c, d, a, b, k[2], 17, 606105819);
            b = ff(b, c, d, a, k[3], 22, -1044525330);
            a = ff(a, b, c, d, k[4], 7, -176418897);
            d = ff(d, a, b, c, k[5], 12, 1200080426);
            c = ff(c, d, a, b, k[6], 17, -1473231341);
            b = ff(b, c, d, a, k[7], 22, -45705983);
            a = ff(a, b, c, d, k[8], 7, 1770035416);
            d = ff(d, a, b, c, k[9], 12, -1958414417);
            c = ff(c, d, a, b, k[10], 17, -42063);
            b = ff(b, c, d, a, k[11], 22, -1990404162);
            a = ff(a, b, c, d, k[12], 7, 1804603682);
            d = ff(d, a, b, c, k[13], 12, -40341101);
            c = ff(c, d, a, b, k[14], 17, -1502002290);
            b = ff(b, c, d, a, k[15], 22, 1236535329);
            
            a = gg(a, b, c, d, k[1], 5, -165796510);
            d = gg(d, a, b, c, k[6], 9, -1069501632);
            c = gg(c, d, a, b, k[11], 14, 643717713);
            b = gg(b, c, d, a, k[0], 20, -373897302);
            a = gg(a, b, c, d, k[5], 5, -701558691);
            d = gg(d, a, b, c, k[10], 9, 38016083);
            c = gg(c, d, a, b, k[15], 14, -660478335);
            b = gg(b, c, d, a, k[4], 20, -405537848);
            a = gg(a, b, c, d, k[9], 5, 568446438);
            d = gg(d, a, b, c, k[14], 9, -1019803690);
            c = gg(c, d, a, b, k[3], 14, -187363961);
            b = gg(b, c, d, a, k[8], 20, 1163531501);
            a = gg(a, b, c, d, k[13], 5, -1444681467);
            d = gg(d, a, b, c, k[2], 9, -51403784);
            c = gg(c, d, a, b, k[7], 14, 1735328473);
            b = gg(b, c, d, a, k[12], 20, -1926607734);
            
            a = hh(a, b, c, d, k[5], 4, -378558);
            d = hh(d, a, b, c, k[8], 11, -2022574463);
            c = hh(c, d, a, b, k[11], 16, 1839030562);
            b = hh(b, c, d, a, k[14], 23, -35309556);
            a = hh(a, b, c, d, k[1], 4, -1530992060);
            d = hh(d, a, b, c, k[4], 11, 1272893353);
            c = hh(c, d, a, b, k[7], 16, -155497632);
            b = hh(b, c, d, a, k[10], 23, -1094730640);
            a = hh(a, b, c, d, k[13], 4, 681279174);
            d = hh(d, a, b, c, k[0], 11, -358537222);
            c = hh(c, d, a, b, k[3], 16, -722521979);
            b = hh(b, c, d, a, k[6], 23, 76029189);
            a = hh(a, b, c, d, k[9], 4, -640364487);
            d = hh(d, a, b, c, k[12], 11, -421815835);
            c = hh(c, d, a, b, k[15], 16, 530742520);
            b = hh(b, c, d, a, k[2], 23, -995338651);
            
            a = ii(a, b, c, d, k[0], 6, -198630844);
            d = ii(d, a, b, c, k[7], 10, 1126891415);
            c = ii(c, d, a, b, k[14], 15, -1416354905);
            b = ii(b, c, d, a, k[5], 21, -57434055);
            a = ii(a, b, c, d, k[12], 6, 1700485571);
            d = ii(d, a, b, c, k[3], 10, -1894986606);
            c = ii(c, d, a, b, k[10], 15, -1051523);
            b = ii(b, c, d, a, k[1], 21, -2054922799);
            a = ii(a, b, c, d, k[8], 6, 1873313359);
            d = ii(d, a, b, c, k[15], 10, -30611744);
            c = ii(c, d, a, b, k[6], 15, -1560198380);
            b = ii(b, c, d, a, k[13], 21, 1309151649);
            a = ii(a, b, c, d, k[4], 6, -145523070);
            d = ii(d, a, b, c, k[11], 10, -1120210379);
            c = ii(c, d, a, b, k[2], 15, 718787259);
            b = ii(b, c, d, a, k[9], 21, -343485551);
            
            x[0] = add32(a, x[0]);
            x[1] = add32(b, x[1]);
            x[2] = add32(c, x[2]);
            x[3] = add32(d, x[3]);
        }
        
        function cmn(q, a, b, x, s, t) {
            a = add32(add32(a, q), add32(x, t));
            return add32((a << s) | (a >>> (32 - s)), b);
        }
        
        function ff(a, b, c, d, x, s, t) {
            return cmn((b & c) | ((~b) & d), a, b, x, s, t);
        }
        
        function gg(a, b, c, d, x, s, t) {
            return cmn((b & d) | (c & (~d)), a, b, x, s, t);
        }
        
        function hh(a, b, c, d, x, s, t) {
            return cmn(b ^ c ^ d, a, b, x, s, t);
        }
        
        function ii(a, b, c, d, x, s, t) {
            return cmn(c ^ (b | (~d)), a, b, x, s, t);
        }
        
        function md51(s) {
            var txt = '';
            var n = s.length,
            state = [1732584193, -271733879, -1732584194, 271733878], i;
            for (i = 64; i <= s.length; i += 64) {
                md5cycle(state, md5blk(s.substring(i - 64, i)));
            }
            s = s.substring(i - 64);
            var tail = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
            for (i = 0; i < s.length; i++)
                tail[i >> 2] |= s.charCodeAt(i) << ((i % 4) << 3);
            tail[i >> 2] |= 0x80 << ((i % 4) << 3);
            if (i > 55) {
                md5cycle(state, tail);
                for (i = 0; i < 16; i++) tail[i] = 0;
            }
            tail[14] = n * 8;
            md5cycle(state, tail);
            return state;
        }
        
        function md5blk(s) {
            var md5blks = [], i;
            for (i = 0; i < 64; i += 4) {
                md5blks[i >> 2] = s.charCodeAt(i) + (s.charCodeAt(i + 1) << 8) + (s.charCodeAt(i + 2) << 16) + (s.charCodeAt(i + 3) << 24);
            }
            return md5blks;
        }
        
        function rhex(n) {
            var hex_chr = '0123456789abcdef'.split('');
            var s = '', j = 0;
            for (; j < 4; j++)
                s += hex_chr[(n >> (j * 8 + 4)) & 0x0F] + hex_chr[(n >> (j * 8)) & 0x0F];
            return s;
        }
        
        function hex(x) {
            for (var i = 0; i < x.length; i++)
                x[i] = rhex(x[i]);
            return x.join('');
        }
        
        function add32(a, b) {
            return (a + b) & 0xFFFFFFFF;
        }
        
        return hex(md51(str));
    }

    // Add CSS styles for tip buttons and popup
    function addTipButtonStyles() {
        if (document.querySelector('#tip-button-styles')) return;

        const style = document.createElement('style');
        style.id = 'tip-button-styles';
        style.textContent = `
            /* Persistent tip button for chat */
            .persistent-tip-btn {
                position: fixed;
                bottom: 80px;
                right: 16px;
                width: 44px;
                height: 44px;
                border-radius: 50%;
                background: linear-gradient(135deg, #ff1493, #ff69b4);
                border: none;
                color: #fff;
                font-size: 24px;
                cursor: pointer;
                box-shadow: 0 4px 16px rgba(255, 20, 147, 0.4);
                z-index: 1000;
                transition: all 0.3s ease;
            }
            
            .persistent-tip-btn:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(255, 20, 147, 0.6);
            }
            
            /* Feed page tip button */
            .feed-tip-btn {
                padding: 8px 16px;
                background: linear-gradient(135deg, #ff1493, #ff69b4);
                border: none;
                border-radius: 20px;
                color: #fff;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
                margin-left: auto;
            }
            
            .feed-tip-btn:hover {
                background: linear-gradient(135deg, #e6127a, #ff1493);
                transform: translateY(-1px);
            }
            
            /* Tip popup overlay */
            .tip-button-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                backdrop-filter: blur(4px);
                animation: tipFadeIn 0.3s ease;
            }
            
            @keyframes tipFadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            /* Tip popup */
            .tip-button-popup {
                background: #0d1b2a;
                border: 1px solid #1a2a3a;
                border-radius: 16px;
                width: 90%;
                max-width: 400px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
                color: #fff;
                overflow: hidden;
                animation: tipSlideUp 0.3s ease;
            }
            
            @keyframes tipSlideUp {
                from { transform: translateY(20px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            
            /* Popup header */
            .tip-button-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 20px 24px;
                border-bottom: 1px solid #1a2a3a;
            }
            
            .tip-button-header h3 {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
                color: #ff1493;
            }
            
            .tip-button-close {
                background: none;
                border: none;
                color: #666;
                font-size: 24px;
                cursor: pointer;
                padding: 0;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                transition: all 0.2s ease;
            }
            
            .tip-button-close:hover {
                color: #fff;
                background: #1a2a3a;
            }
            
            /* Popup content */
            .tip-button-content {
                padding: 24px;
            }
            
            /* Preset amounts */
            .tip-preset-amounts {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
                margin-bottom: 24px;
            }
            
            .tip-preset-btn {
                padding: 16px;
                background: #1a2a3a;
                border: 2px solid transparent;
                border-radius: 12px;
                color: #fff;
                font-size: 18px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
                position: relative;
            }
            
            .tip-preset-btn:hover {
                background: #243447;
                border-color: #00b4ff;
            }
            
            .tip-preset-btn.selected {
                background: linear-gradient(135deg, #ff1493, #ff69b4);
                border-color: #ff1493;
            }
            
            /* Custom amount */
            .tip-custom-amount {
                margin-bottom: 24px;
            }
            
            .tip-custom-amount label {
                display: block;
                margin-bottom: 8px;
                color: #ccc;
                font-weight: 500;
            }
            
            .tip-custom-input {
                display: flex;
                align-items: center;
                background: #1a2a3a;
                border: 2px solid transparent;
                border-radius: 12px;
                padding: 0 16px;
                transition: all 0.2s ease;
            }
            
            .tip-custom-input:focus-within {
                border-color: #00b4ff;
                background: #243447;
            }
            
            .tip-custom-input span {
                color: #999;
                font-size: 18px;
                font-weight: 600;
            }
            
            .tip-custom-input input {
                background: none;
                border: none;
                color: #fff;
                font-size: 18px;
                font-weight: 600;
                padding: 16px 8px;
                flex: 1;
                outline: none;
            }
            
            .tip-custom-input input::placeholder {
                color: #666;
            }
            
            .tip-custom-input input.error {
                color: #ff6b6b;
            }
            
            /* Send button */
            .tip-send-btn {
                width: 100%;
                padding: 16px;
                background: linear-gradient(135deg, #ff1493, #ff69b4);
                border: none;
                border-radius: 12px;
                color: #fff;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
                margin-bottom: 16px;
            }
            
            .tip-send-btn:hover:not(:disabled) {
                background: linear-gradient(135deg, #e6127a, #ff1493);
                transform: translateY(-2px);
            }
            
            .tip-send-btn:disabled {
                background: #333;
                color: #666;
                cursor: not-allowed;
                transform: none;
            }
            
            /* Error message */
            .tip-error-msg {
                background: #4a1a1a;
                border: 1px solid #ff4444;
                border-radius: 8px;
                padding: 12px;
                color: #ffaaaa;
                font-size: 14px;
                text-align: center;
            }
            
            /* Processing state */
            .tip-processing {
                padding: 40px;
                text-align: center;
                color: #ccc;
            }
            
            .tip-spinner {
                width: 32px;
                height: 32px;
                border: 3px solid #333;
                border-top: 3px solid #ff1493;
                border-radius: 50%;
                animation: tipSpin 1s linear infinite;
                margin: 0 auto 16px;
            }
            
            @keyframes tipSpin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            /* Success state */
            .tip-success-content {
                padding: 40px 24px;
                text-align: center;
            }
            
            .tip-success-icon {
                font-size: 48px;
                margin-bottom: 16px;
            }
            
            .tip-success-content h3 {
                color: #ff1493;
                margin: 0 0 12px 0;
                font-size: 24px;
            }
            
            .tip-success-content p {
                color: #ccc;
                margin: 0 0 24px 0;
                font-size: 16px;
            }
            
            .tip-success-done {
                background: linear-gradient(135deg, #ff1493, #ff69b4);
                color: #fff;
                border: none;
                padding: 12px 24px;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .tip-success-done:hover {
                background: linear-gradient(135deg, #e6127a, #ff1493);
            }
            
            /* Chat tip notification */
            .chat-tip-notification {
                text-align: center;
                padding: 8px 16px;
                margin: 8px auto;
                background: linear-gradient(135deg, #ffa500, #ff8c00);
                color: #fff;
                border-radius: 20px;
                font-size: 14px;
                font-weight: 600;
                max-width: 200px;
                animation: tipSlideUp 0.3s ease;
            }
            
            /* Mobile responsive */
            @media (max-width: 480px) {
                .tip-button-popup {
                    width: 95%;
                    margin: 0 10px;
                }
                
                .tip-preset-amounts {
                    grid-template-columns: 1fr 1fr;
                }
                
                .persistent-tip-btn {
                    bottom: 80px; /* Account for mobile nav */
                    right: 16px;
                    width: 48px;
                    height: 48px;
                    font-size: 20px;
                }
            }
            
            /* Position feed tip button correctly */
            [class*="creatorInfo"], [class*="creatorHeader"] {
                display: flex !important;
                align-items: center !important;
                gap: 12px !important;
            }
        `;
        
        document.head.appendChild(style);
    }

    // Disable per-message tip icons from tip-confirm.js
    function disablePerMessageTips() {
        // Override the auto-wire function to prevent it from running
        if (window.TipConfirm) {
            // Save original function
            window.TipConfirm._originalAutoWire = window.TipConfirm.autoWire;
            // Replace with no-op
            window.TipConfirm.autoWire = function() {
                console.log('Per-message tip icons disabled - using persistent tip buttons');
            };
        }
        
        // Remove existing tip icons from messages
        const removeExistingTipIcons = () => {
            const tipIcons = document.querySelectorAll('[data-tip-amount]');
            tipIcons.forEach(icon => {
                if (icon.textContent === '💰') {
                    icon.remove();
                }
            });
        };
        
        // Remove immediately and set up observer for dynamic content
        removeExistingTipIcons();
        
        // Observer to remove tip icons as they're added
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        const tipIcons = node.querySelectorAll ? node.querySelectorAll('[data-tip-amount]') : [];
                        tipIcons.forEach(icon => {
                            if (icon.textContent === '💰') {
                                icon.remove();
                            }
                        });
                    }
                });
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initTipButtons();
            disablePerMessageTips();
        });
    } else {
        initTipButtons();
        disablePerMessageTips();
    }

})();