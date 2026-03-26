/**
 * PPV Chat Frontend - Handles blurred previews and unlock UI
 * File: js/ppv-chat.js
 */

class PPVChat {
    constructor() {
        this.init();
    }

    init() {
        // Initialize PPV message handling when page loads
        document.addEventListener('DOMContentLoaded', () => {
            this.processExistingPPVMessages();
        });

        // Listen for new messages (if using dynamic loading)
        document.addEventListener('newMessage', (e) => {
            if (e.detail && e.detail.message) {
                this.processPPVMessage(e.detail.message);
            }
        });
    }

    processExistingPPVMessages() {
        // Find all PPV messages in the chat
        const ppvMessages = document.querySelectorAll('[data-ppv="1"]');
        ppvMessages.forEach(messageEl => {
            const messageData = this.extractMessageData(messageEl);
            if (messageData) {
                this.processPPVMessage(messageData, messageEl);
            }
        });
    }

    extractMessageData(messageEl) {
        // Extract PPV data from DOM element
        return {
            id: messageEl.dataset.messageId,
            is_ppv: messageEl.dataset.ppv === '1',
            is_unlocked: messageEl.dataset.unlocked === '1',
            ppv_price_cents: parseInt(messageEl.dataset.priceCents || '0'),
            ppv_preview_url: messageEl.dataset.previewUrl || '',
            media_url: messageEl.dataset.mediaUrl || '',
            element: messageEl
        };
    }

    processPPVMessage(messageData, messageEl = null) {
        if (!messageData.is_ppv) return;

        const element = messageEl || document.querySelector(`[data-message-id="${messageData.id}"]`);
        if (!element) return;

        if (messageData.is_unlocked) {
            this.showUnlockedContent(messageData, element);
        } else {
            this.showLockedPreview(messageData, element);
        }
    }

    showLockedPreview(messageData, element) {
        const price = (messageData.ppv_price_cents / 100).toFixed(2);
        const previewUrl = messageData.ppv_preview_url;

        // Find or create the image container
        let imageContainer = element.querySelector('.ppv-image-container');
        if (!imageContainer) {
            imageContainer = document.createElement('div');
            imageContainer.className = 'ppv-image-container';
            element.appendChild(imageContainer);
        }

        imageContainer.innerHTML = `
            <div class="ppv-preview-wrapper">
                <div class="ppv-preview-image">
                    <img src="${previewUrl}" alt="Exclusive content preview" class="ppv-blurred" />
                    <div class="ppv-overlay">
                        <div class="ppv-lock-icon">🔒</div>
                        <div class="ppv-price-badge">$${price}</div>
                        <button class="ppv-unlock-btn" onclick="ppvChat.showUnlockConfirm('${messageData.id}', '${price}')">
                            Unlock 🔓
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Add CSS styles if not already present
        this.addPPVStyles();
    }

    showUnlockedContent(messageData, element) {
        // Find the image container
        let imageContainer = element.querySelector('.ppv-image-container');
        if (!imageContainer) {
            imageContainer = document.createElement('div');
            imageContainer.className = 'ppv-image-container';
            element.appendChild(imageContainer);
        }

        // Determine if it's a video or image
        const mediaUrl = messageData.media_url;
        const isVideo = mediaUrl.match(/\.(mp4|mov|avi|webm)$/i);

        if (isVideo) {
            imageContainer.innerHTML = `
                <video class="ppv-unlocked-video" controls>
                    <source src="${mediaUrl}" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            `;
        } else {
            imageContainer.innerHTML = `
                <img src="${mediaUrl}" alt="Exclusive content" class="ppv-unlocked-image" />
            `;
        }
    }

    showUnlockConfirm(messageId, price) {
        // Create confirmation modal (similar to TipConfirm style)
        const modal = document.createElement('div');
        modal.className = 'ppv-confirm-modal';
        modal.innerHTML = `
            <div class="ppv-confirm-overlay" onclick="ppvChat.closeUnlockConfirm()"></div>
            <div class="ppv-confirm-content">
                <h3>Unlock Exclusive Content</h3>
                <p>This will charge <strong>$${price}</strong> to unlock this exclusive photo/video.</p>
                <div class="ppv-confirm-buttons">
                    <button class="ppv-confirm-cancel" onclick="ppvChat.closeUnlockConfirm()">
                        Cancel
                    </button>
                    <button class="ppv-confirm-unlock" onclick="ppvChat.unlockContent('${messageId}')">
                        Unlock for $${price} 🔓
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        setTimeout(() => modal.classList.add('show'), 10);
    }

    closeUnlockConfirm() {
        const modal = document.querySelector('.ppv-confirm-modal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => modal.remove(), 300);
        }
    }

    async unlockContent(messageId) {
        const unlockBtn = document.querySelector('.ppv-confirm-unlock');
        if (unlockBtn) {
            unlockBtn.textContent = 'Unlocking...';
            unlockBtn.disabled = true;
        }

        try {
            const response = await fetch('/api/payments/ppv-unlock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`
                },
                body: JSON.stringify({
                    message_id: parseInt(messageId)
                })
            });

            const result = await response.json();

            this.closeUnlockConfirm();

            if (result.success) {
                // Update the message with unlocked content
                const messageEl = document.querySelector(`[data-message-id="${messageId}"]`);
                if (messageEl) {
                    messageEl.dataset.unlocked = '1';
                    messageEl.dataset.mediaUrl = result.media_url;

                    const messageData = this.extractMessageData(messageEl);
                    messageData.is_unlocked = true;
                    messageData.media_url = result.media_url;

                    this.showUnlockedContent(messageData, messageEl);
                }

                // Show success notification
                this.showNotification('Content unlocked! 🎉', 'success');

            } else {
                // Show error notification
                this.showNotification(`Failed to unlock: ${result.error}`, 'error');
            }

        } catch (error) {
            this.closeUnlockConfirm();
            this.showNotification('Network error. Please try again.', 'error');
            console.error('PPV unlock error:', error);
        }
    }

    getAuthToken() {
        // Get JWT token from localStorage or cookie
        return localStorage.getItem('auth_token') || 
               document.cookie.split('; ')
                   .find(row => row.startsWith('auth_token='))
                   ?.split('=')[1] || '';
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `ppv-notification ppv-notification-${type}`;
        notification.textContent = message;

        // Add to page
        document.body.appendChild(notification);

        // Show and auto-hide
        setTimeout(() => notification.classList.add('show'), 10);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    addPPVStyles() {
        // Check if styles already exist
        if (document.querySelector('#ppv-chat-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'ppv-chat-styles';
        styles.textContent = `
            /* PPV Chat Styles */
            .ppv-preview-wrapper {
                position: relative;
                display: inline-block;
                margin: 10px 0;
                border-radius: 10px;
                overflow: hidden;
                max-width: 300px;
            }

            .ppv-preview-image {
                position: relative;
            }

            .ppv-blurred {
                filter: blur(20px);
                width: 100%;
                height: auto;
                display: block;
            }

            .ppv-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                background: rgba(0, 0, 0, 0.5);
                color: white;
                text-align: center;
            }

            .ppv-lock-icon {
                font-size: 2em;
                margin-bottom: 10px;
            }

            .ppv-price-badge {
                background: #ff1493;
                padding: 5px 10px;
                border-radius: 15px;
                font-weight: bold;
                margin-bottom: 10px;
                font-size: 1.1em;
            }

            .ppv-unlock-btn {
                background: linear-gradient(45deg, #ff1493, #ff69b4);
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 25px;
                font-weight: bold;
                cursor: pointer;
                transition: transform 0.2s;
                font-size: 14px;
            }

            .ppv-unlock-btn:hover {
                transform: scale(1.05);
            }

            .ppv-unlock-btn:active {
                transform: scale(0.95);
            }

            .ppv-unlocked-image, .ppv-unlocked-video {
                width: 100%;
                height: auto;
                border-radius: 10px;
                margin: 10px 0;
                max-width: 300px;
            }

            /* Confirmation Modal */
            .ppv-confirm-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 9999;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .ppv-confirm-modal.show {
                opacity: 1;
            }

            .ppv-confirm-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
            }

            .ppv-confirm-content {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                padding: 30px;
                border-radius: 15px;
                text-align: center;
                max-width: 400px;
                width: 90%;
            }

            .ppv-confirm-content h3 {
                margin-top: 0;
                color: #333;
            }

            .ppv-confirm-buttons {
                display: flex;
                gap: 15px;
                margin-top: 20px;
                justify-content: center;
            }

            .ppv-confirm-cancel {
                background: #ccc;
                color: #333;
                border: none;
                padding: 10px 20px;
                border-radius: 20px;
                cursor: pointer;
                font-weight: bold;
            }

            .ppv-confirm-unlock {
                background: linear-gradient(45deg, #ff1493, #ff69b4);
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 20px;
                cursor: pointer;
                font-weight: bold;
            }

            .ppv-confirm-unlock:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            /* Notification */
            .ppv-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 10px;
                color: white;
                font-weight: bold;
                z-index: 10000;
                transform: translateX(400px);
                transition: transform 0.3s ease;
            }

            .ppv-notification.show {
                transform: translateX(0);
            }

            .ppv-notification-success {
                background: #28a745;
            }

            .ppv-notification-error {
                background: #dc3545;
            }

            .ppv-notification-info {
                background: #17a2b8;
            }
        `;

        document.head.appendChild(styles);
    }
}

// Initialize PPV Chat
const ppvChat = new PPVChat();