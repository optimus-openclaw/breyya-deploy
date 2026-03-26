/**
 * tip-confirm.js
 * Client-side confirmation popup for CBPT charges
 * Dark theme matching the site
 */

(function() {
    'use strict';

    // Global tip confirmation handler
    window.TipConfirm = {
        // Check if user has a stored payment method
        async checkPaymentMethod(fanUserId) {
            try {
                const response = await fetch('/api/payments/check-saved-card.php', {
                    credentials: 'include'
                });

                if (!response.ok) {
                    return null;
                }

                const data = await response.json();
                return data.has_saved_card ? data : null;
            } catch (error) {
                console.error('Failed to check payment method:', error);
                return null;
            }
        },

        // Show tip confirmation popup
        async showTipConfirm(fanUserId, amount, description = 'Tip') {
            // Check if user has stored payment method
            const paymentMethod = await this.checkPaymentMethod(fanUserId);
            
            if (!paymentMethod) {
                // No stored payment method - redirect to FlexForm
                this.redirectToFlexForm(amount, description);
                return;
            }

            // Show CBPT confirmation popup
            this.showCBPTPopup(fanUserId, amount, description, paymentMethod);
        },

        // Show CBPT confirmation popup with stored card
        showCBPTPopup(fanUserId, amount, description, paymentMethod) {
            const { card_last_four, card_type } = paymentMethod;
            const formattedAmount = parseFloat(amount).toFixed(2);
            
            // Create popup overlay
            const overlay = document.createElement('div');
            overlay.className = 'tip-confirm-overlay';
            overlay.innerHTML = `
                <div class="tip-confirm-popup">
                    <div class="tip-confirm-header">
                        <h3>💕 ${description}</h3>
                        <button class="tip-confirm-close" type="button">×</button>
                    </div>
                    
                    <div class="tip-confirm-content">
                        <div class="tip-confirm-amount">
                            $${formattedAmount}
                        </div>
                        
                        <div class="tip-confirm-billing">
                            <p><strong>Billing Terms:</strong></p>
                            <p>$${formattedAmount} one-time charge (non-recurring)</p>
                        </div>
                        
                        <div class="tip-confirm-card">
                            <p><strong>Payment Method:</strong></p>
                            <p>
                                ${card_type ? `${card_type} ` : ''}ending in ****${card_last_four}
                            </p>
                        </div>
                        
                        <div class="tip-confirm-question">
                            Send ${description.toLowerCase()} to Breyya?
                        </div>
                    </div>
                    
                    <div class="tip-confirm-actions">
                        <button class="tip-confirm-cancel" type="button">Cancel</button>
                        <button class="tip-confirm-submit" type="button">
                            💕 Confirm $${formattedAmount}
                        </button>
                    </div>
                    
                    <div class="tip-confirm-processing" style="display: none;">
                        <div class="tip-spinner"></div>
                        <p>Processing payment...</p>
                    </div>
                </div>
            `;

            // Add styles if not already added
            this.addStyles();

            // Add to document
            document.body.appendChild(overlay);

            // Event listeners
            const popup = overlay.querySelector('.tip-confirm-popup');
            const closeBtn = overlay.querySelector('.tip-confirm-close');
            const cancelBtn = overlay.querySelector('.tip-confirm-cancel');
            const submitBtn = overlay.querySelector('.tip-confirm-submit');
            const processing = overlay.querySelector('.tip-confirm-processing');

            // Close handlers
            const closePopup = () => {
                overlay.remove();
            };

            closeBtn.onclick = closePopup;
            cancelBtn.onclick = closePopup;
            
            overlay.onclick = (e) => {
                if (e.target === overlay) closePopup();
            };

            // Submit handler
            submitBtn.onclick = async () => {
                await this.processCBPTCharge(fanUserId, amount, description, overlay, processing, submitBtn);
            };

            // Focus the submit button
            setTimeout(() => submitBtn.focus(), 100);
        },

        // Process the CBPT charge
        async processCBPTCharge(fanUserId, amount, description, overlay, processing, submitBtn) {
            try {
                // Show processing state
                processing.style.display = 'block';
                submitBtn.disabled = true;
                submitBtn.textContent = 'Processing...';

                const response = await fetch('/api/payments/cbpt-charge.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        fan_user_id: fanUserId,
                        amount: amount,
                        description: description
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Success!
                    this.showSuccess(overlay, amount);
                } else {
                    // Payment failed
                    this.showError(overlay, data.error || 'Payment failed');
                }

            } catch (error) {
                console.error('CBPT charge error:', error);
                this.showError(overlay, 'Network error. Please try again.');
            }
        },

        // Show success message
        showSuccess(overlay, amount) {
            const popup = overlay.querySelector('.tip-confirm-popup');
            popup.innerHTML = `
                <div class="tip-confirm-success">
                    <div class="tip-success-icon">💕</div>
                    <h3>Tip sent!</h3>
                    <p>Your $${parseFloat(amount).toFixed(2)} tip was sent to Breyya.</p>
                    <button class="tip-confirm-done" type="button">Done</button>
                </div>
            `;

            const doneBtn = popup.querySelector('.tip-confirm-done');
            doneBtn.onclick = () => overlay.remove();
            doneBtn.focus();

            // Auto-close after 3 seconds
            setTimeout(() => overlay.remove(), 3000);
        },

        // Show error message
        showError(overlay, errorMessage) {
            const popup = overlay.querySelector('.tip-confirm-popup');
            const processing = popup.querySelector('.tip-confirm-processing');
            const submitBtn = popup.querySelector('.tip-confirm-submit');

            // Hide processing
            processing.style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.textContent = '💕 Confirm';

            // Show error
            let errorDiv = popup.querySelector('.tip-confirm-error');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'tip-confirm-error';
                popup.querySelector('.tip-confirm-content').appendChild(errorDiv);
            }

            errorDiv.innerHTML = `
                <p><strong>Payment Failed:</strong></p>
                <p>${errorMessage}</p>
            `;
            errorDiv.style.display = 'block';
        },

        // Redirect to FlexForm for first-time users
        redirectToFlexForm(amount, description) {
            const formattedAmount = parseFloat(amount).toFixed(2);
            
            // Calculate FlexForm digest for tips (sub-account 0001)
            const initialPeriod = '3';
            const currencyCode = '840';
            const salt = 'XXDzs2W4u9XtgNnXNQ4FyUgk';
            const digestString = formattedAmount + initialPeriod + currencyCode + salt;
            const digest = this.simpleMD5(digestString);
            
            const flexFormUrl = `https://api.ccbill.com/wap-frontflex/flexforms/d6c111d7-3565-4d8a-a3d7-211539a585f3?clientSubacc=0001&initialPrice=${formattedAmount}&initialPeriod=${initialPeriod}&recurringPrice=0.00&recurringPeriod=0&numRebills=0&currencyCode=${currencyCode}&formDigest=${digest}`;
            
            window.location.href = flexFormUrl;
        },

        // Simple MD5 implementation for FlexForm digest
        simpleMD5(str) {
            // Basic MD5 implementation - for production, use crypto-js
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
        },

        // Add CSS styles
        addStyles() {
            if (document.querySelector('#tip-confirm-styles')) return;

            const style = document.createElement('style');
            style.id = 'tip-confirm-styles';
            style.textContent = `
                .tip-confirm-overlay {
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
                }

                .tip-confirm-popup {
                    background: #1a1a1a;
                    border: 1px solid #333;
                    border-radius: 12px;
                    width: 90%;
                    max-width: 420px;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
                    color: #fff;
                    overflow: hidden;
                }

                .tip-confirm-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 20px 24px;
                    border-bottom: 1px solid #333;
                }

                .tip-confirm-header h3 {
                    margin: 0;
                    font-size: 20px;
                    font-weight: 600;
                    color: #ff1493;
                }

                .tip-confirm-close {
                    background: none;
                    border: none;
                    color: #999;
                    font-size: 24px;
                    cursor: pointer;
                    padding: 0;
                    width: 30px;
                    height: 30px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .tip-confirm-close:hover {
                    color: #fff;
                }

                .tip-confirm-content {
                    padding: 24px;
                }

                .tip-confirm-amount {
                    font-size: 32px;
                    font-weight: 700;
                    color: #ff1493;
                    text-align: center;
                    margin-bottom: 20px;
                }

                .tip-confirm-billing,
                .tip-confirm-card {
                    margin-bottom: 16px;
                    padding: 12px;
                    background: #2a2a2a;
                    border-radius: 8px;
                    font-size: 14px;
                }

                .tip-confirm-billing p:first-child,
                .tip-confirm-card p:first-child {
                    margin: 0 0 4px 0;
                    color: #ccc;
                }

                .tip-confirm-billing p:last-child,
                .tip-confirm-card p:last-child {
                    margin: 0;
                    color: #fff;
                }

                .tip-confirm-question {
                    text-align: center;
                    font-size: 16px;
                    color: #ccc;
                    margin-top: 16px;
                }

                .tip-confirm-actions {
                    display: flex;
                    gap: 12px;
                    padding: 0 24px 24px;
                }

                .tip-confirm-cancel,
                .tip-confirm-submit {
                    flex: 1;
                    padding: 12px 20px;
                    border: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }

                .tip-confirm-cancel {
                    background: #333;
                    color: #ccc;
                }

                .tip-confirm-cancel:hover {
                    background: #444;
                    color: #fff;
                }

                .tip-confirm-submit {
                    background: linear-gradient(45deg, #ff1493, #ff69b4);
                    color: #fff;
                }

                .tip-confirm-submit:hover:not(:disabled) {
                    background: linear-gradient(45deg, #e6127a, #ff1493);
                }

                .tip-confirm-submit:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                }

                .tip-confirm-processing {
                    padding: 20px;
                    text-align: center;
                    color: #ccc;
                }

                .tip-spinner {
                    width: 32px;
                    height: 32px;
                    border: 3px solid #333;
                    border-top: 3px solid #ff1493;
                    border-radius: 50%;
                    animation: tip-spin 1s linear infinite;
                    margin: 0 auto 12px;
                }

                @keyframes tip-spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }

                .tip-confirm-success {
                    padding: 40px 24px;
                    text-align: center;
                }

                .tip-success-icon {
                    font-size: 48px;
                    margin-bottom: 16px;
                }

                .tip-confirm-success h3 {
                    color: #ff1493;
                    margin: 0 0 12px 0;
                    font-size: 24px;
                }

                .tip-confirm-success p {
                    color: #ccc;
                    margin: 0 0 24px 0;
                }

                .tip-confirm-done {
                    background: linear-gradient(45deg, #ff1493, #ff69b4);
                    color: #fff;
                    border: none;
                    padding: 12px 24px;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                }

                .tip-confirm-error {
                    margin-top: 16px;
                    padding: 12px;
                    background: #4a1a1a;
                    border: 1px solid #ff4444;
                    border-radius: 8px;
                    color: #ffaaaa;
                    font-size: 14px;
                }

                .tip-confirm-error p:first-child {
                    margin: 0 0 4px 0;
                    font-weight: 600;
                }

                .tip-confirm-error p:last-child {
                    margin: 0;
                }

                @media (max-width: 480px) {
                    .tip-confirm-popup {
                        width: 95%;
                        margin: 0 20px;
                    }
                    
                    .tip-confirm-amount {
                        font-size: 28px;
                    }
                    
                    .tip-confirm-actions {
                        flex-direction: column;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    };

    // Auto-wire existing tip buttons
    document.addEventListener('DOMContentLoaded', function() {
        // Look for existing tip buttons and wire them up
        const tipButtons = document.querySelectorAll('[data-tip-amount]');
        
        tipButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const amount = this.dataset.tipAmount;
                const description = this.dataset.tipDescription || 'Tip';
                const fanUserId = this.dataset.fanUserId || getCurrentUserId();
                
                if (fanUserId && amount) {
                    window.TipConfirm.showTipConfirm(fanUserId, amount, description);
                }
            });
        });
    });

    // Helper to get current user ID from JWT token
    function getCurrentUserId() {
        try {
            var token = getCookie('breyya_token') || localStorage.getItem('breyya_token') || localStorage.getItem('token') || '';
            if (!token) return null;
            
            // Decode JWT payload (basic decode, no verification)
            var parts = token.split('.');
            if (parts.length !== 3) return null;
            
            var payload = JSON.parse(atob(parts[1]));
            return payload.sub || null;
        } catch(e) {
            return null;
        }
    }

    // Helper to get cookie value
    function getCookie(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for(var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }
})();