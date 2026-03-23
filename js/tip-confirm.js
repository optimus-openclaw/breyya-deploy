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
                const response = await fetch(`/api/payments/check-payment-method.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({ fan_user_id: fanUserId })
                });

                if (!response.ok) {
                    return null;
                }

                const data = await response.json();
                return data.success ? data.payment_method : null;
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
            // Use existing tip link API
            fetch(`/api/payments/create-tip-link.php?amount=${amount}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.url) {
                        window.location.href = data.data.url;
                    } else {
                        alert('Failed to create payment link. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('FlexForm redirect error:', error);
                    alert('Failed to create payment link. Please try again.');
                });
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