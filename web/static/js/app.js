// Orc Social - Client-side JavaScript (≤150 lines)
// Privacy-first social network functionality

(function() {
    'use strict';

    // Link preview functionality
    window.loadPreview = function(url, postId) {
        const previewDiv = document.getElementById('preview-' + postId);
        if (!previewDiv) return;
        
        // Show loading state
        previewDiv.innerHTML = '<p class="text-muted">Loading preview...</p>';
        previewDiv.classList.remove('hidden');
        
        // Get CSRF token
        const csrfToken = getCsrfToken();
        if (!csrfToken) {
            previewDiv.innerHTML = '<p class="text-error">Security error</p>';
            return;
        }
        
        // Create form data
        const formData = new FormData();
        formData.append('url', url);
        formData.append('csrf_token', csrfToken);
        
        // Fetch preview
        fetch('/preview', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.title || data.description) {
                previewDiv.innerHTML = `
                    <div class="border border-border rounded p-3 bg-surface">
                        <h4 class="font-semibold mb-1">${escapeHtml(data.title)}</h4>
                        ${data.description ? `<p class="text-muted text-sm">${escapeHtml(data.description)}</p>` : ''}
                        <a href="${escapeHtml(data.url)}" target="_blank" class="text-primary text-sm hover:underline">
                            ${escapeHtml(data.url)}
                        </a>
                    </div>
                `;
            } else {
                previewDiv.innerHTML = '<p class="text-muted">No preview available</p>';
            }
        })
        .catch(error => {
            previewDiv.innerHTML = '<p class="text-error">Failed to load preview</p>';
            console.error('Preview error:', error);
        });
    };

    // Get CSRF token from cookie
    function getCsrfToken() {
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'csrf_token') {
                return decodeURIComponent(value);
            }
        }
        return null;
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Character counter for textareas
    function setupCharacterCounters() {
        const textareas = document.querySelectorAll('textarea[maxlength]');
        textareas.forEach(textarea => {
            const maxLength = parseInt(textarea.getAttribute('maxlength'));
            const counter = document.createElement('div');
            counter.className = 'text-muted text-sm mt-1';
            counter.textContent = `0/${maxLength}`;
            textarea.parentNode.insertBefore(counter, textarea.nextSibling);
            
            textarea.addEventListener('input', () => {
                const remaining = maxLength - textarea.value.length;
                counter.textContent = `${textarea.value.length}/${maxLength}`;
                counter.className = remaining < 50 ? 'text-warning text-sm mt-1' : 'text-muted text-sm mt-1';
            });
        });
    }

    // Auto-resize textareas
    function setupAutoResize() {
        const textareas = document.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            textarea.style.resize = 'vertical';
            textarea.addEventListener('input', () => {
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
            });
        });
    }

    // Confirm dangerous actions
    function setupConfirmations() {
        const dangerousButtons = document.querySelectorAll('.btn-danger');
        dangerousButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const action = button.textContent.toLowerCase();
                if (!confirm(`Are you sure you want to ${action}? This action cannot be undone.`)) {
                    e.preventDefault();
                }
            });
        });
    }

    // Form validation
    function setupFormValidation() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = 'var(--color-error)';
                        isValid = false;
                    } else {
                        field.style.borderColor = '';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
    }

    // Keyboard shortcuts
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + Enter to submit forms
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const activeElement = document.activeElement;
                if (activeElement.tagName === 'TEXTAREA') {
                    const form = activeElement.closest('form');
                    if (form) {
                        form.submit();
                    }
                }
            }
            
            // Escape to close modals/details
            if (e.key === 'Escape') {
                const openDetails = document.querySelectorAll('details[open]');
                openDetails.forEach(details => details.open = false);
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        setupCharacterCounters();
        setupAutoResize();
        setupConfirmations();
        setupFormValidation();
        setupKeyboardShortcuts();
        
        // Focus first input on forms
        const firstInput = document.querySelector('form input:not([type="hidden"]):not([type="submit"])');
        if (firstInput) {
            firstInput.focus();
        }
    }

})();