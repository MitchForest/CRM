/**
 * SuiteCRM Forms Embed Script
 * Allows embedding CRM forms on external websites
 * 
 * Usage:
 * <div id="suitecrm-form" data-form-id="YOUR_FORM_ID"></div>
 * <script src="https://your-crm.com/public/js/forms-embed.js"></script>
 */

(function() {
    'use strict';
    
    // Configuration
    const CRM_BASE_URL = window.SUITECRM_URL || 'http://localhost:8080';
    const API_ENDPOINT = '/api/v8/forms';
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        // Find all form containers
        const containers = document.querySelectorAll('[data-form-id]');
        containers.forEach(container => {
            const formId = container.dataset.formId;
            if (formId) {
                loadForm(formId, container);
            }
        });
    }
    
    async function loadForm(formId, container) {
        try {
            // Show loading state
            container.innerHTML = '<div class="suitecrm-loading">Loading form...</div>';
            
            // Fetch form configuration
            const response = await fetch(`${CRM_BASE_URL}${API_ENDPOINT}/${formId}`);
            if (!response.ok) {
                throw new Error('Form not found');
            }
            
            const formData = await response.json();
            
            // Render form
            renderForm(formData, container);
            
        } catch (error) {
            console.error('Error loading form:', error);
            container.innerHTML = '<div class="suitecrm-error">Unable to load form. Please try again later.</div>';
        }
    }
    
    function renderForm(formData, container) {
        // Create form element
        const form = document.createElement('form');
        form.className = 'suitecrm-form';
        form.dataset.formId = formData.id;
        
        // Add title
        if (formData.name) {
            const title = document.createElement('h3');
            title.className = 'suitecrm-form-title';
            title.textContent = formData.name;
            form.appendChild(title);
        }
        
        // Add description
        if (formData.description) {
            const desc = document.createElement('p');
            desc.className = 'suitecrm-form-description';
            desc.textContent = formData.description;
            form.appendChild(desc);
        }
        
        // Render fields
        formData.fields.forEach(field => {
            const fieldElement = createField(field);
            form.appendChild(fieldElement);
        });
        
        // Add submit button
        const submitBtn = document.createElement('button');
        submitBtn.type = 'submit';
        submitBtn.className = 'suitecrm-form-submit';
        submitBtn.textContent = formData.settings?.submit_button_text || 'Submit';
        form.appendChild(submitBtn);
        
        // Add form styles
        injectStyles();
        
        // Handle form submission
        form.addEventListener('submit', (e) => handleSubmit(e, formData));
        
        // Replace container content
        container.innerHTML = '';
        container.appendChild(form);
    }
    
    function createField(field) {
        const wrapper = document.createElement('div');
        wrapper.className = 'suitecrm-field-wrapper';
        
        // Add label
        if (field.label) {
            const label = document.createElement('label');
            label.className = 'suitecrm-field-label';
            label.textContent = field.label;
            if (field.required) {
                label.innerHTML += ' <span class="required">*</span>';
            }
            wrapper.appendChild(label);
        }
        
        // Create input based on type
        let input;
        switch (field.type) {
            case 'textarea':
                input = document.createElement('textarea');
                input.rows = field.rows || 4;
                break;
                
            case 'select':
                input = document.createElement('select');
                if (field.options) {
                    field.options.forEach(opt => {
                        const option = document.createElement('option');
                        option.value = opt.value || opt;
                        option.textContent = opt.label || opt;
                        input.appendChild(option);
                    });
                }
                break;
                
            case 'checkbox':
                input = document.createElement('input');
                input.type = 'checkbox';
                break;
                
            default:
                input = document.createElement('input');
                input.type = field.type || 'text';
        }
        
        // Set common attributes
        input.name = field.name;
        input.className = 'suitecrm-field-input';
        input.required = field.required || false;
        input.placeholder = field.placeholder || '';
        
        if (field.validation) {
            input.pattern = field.validation;
        }
        
        wrapper.appendChild(input);
        
        return wrapper;
    }
    
    async function handleSubmit(event, formData) {
        event.preventDefault();
        
        const form = event.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        // Disable form and show loading
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
        
        try {
            // Collect form data
            const formDataObj = new FormData(form);
            const data = Object.fromEntries(formDataObj);
            
            // Add metadata
            data._metadata = {
                page_url: window.location.href,
                referrer: document.referrer,
                timestamp: new Date().toISOString(),
            };
            
            // Submit to API
            const response = await fetch(`${CRM_BASE_URL}${API_ENDPOINT}/${formData.id}/submit`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            });
            
            if (!response.ok) {
                throw new Error('Submission failed');
            }
            
            const result = await response.json();
            
            // Show success message
            showSuccess(form, formData.settings?.success_message || 'Thank you for your submission!');
            
            // Trigger custom event
            window.dispatchEvent(new CustomEvent('suitecrm:form:submitted', {
                detail: { formId: formData.id, data: data }
            }));
            
            // Redirect if configured
            if (formData.settings?.redirect_url) {
                setTimeout(() => {
                    window.location.href = formData.settings.redirect_url;
                }, 2000);
            }
            
        } catch (error) {
            console.error('Submission error:', error);
            showError(form, 'There was an error submitting the form. Please try again.');
            
            // Re-enable form
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }
    
    function showSuccess(form, message) {
        form.innerHTML = `
            <div class="suitecrm-success">
                <svg class="success-icon" viewBox="0 0 24 24" width="48" height="48">
                    <path fill="currentColor" d="M9,20.42L2.79,14.21L5.62,11.38L9,14.77L18.88,4.88L21.71,7.71L9,20.42Z" />
                </svg>
                <h3>Success!</h3>
                <p>${message}</p>
            </div>
        `;
    }
    
    function showError(form, message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'suitecrm-error-message';
        errorDiv.textContent = message;
        form.insertBefore(errorDiv, form.firstChild);
        
        setTimeout(() => errorDiv.remove(), 5000);
    }
    
    function injectStyles() {
        if (document.getElementById('suitecrm-form-styles')) {
            return;
        }
        
        const style = document.createElement('style');
        style.id = 'suitecrm-form-styles';
        style.textContent = `
            .suitecrm-form {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            
            .suitecrm-form-title {
                margin-bottom: 10px;
                color: #333;
            }
            
            .suitecrm-form-description {
                margin-bottom: 20px;
                color: #666;
            }
            
            .suitecrm-field-wrapper {
                margin-bottom: 20px;
            }
            
            .suitecrm-field-label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
                color: #333;
            }
            
            .suitecrm-field-label .required {
                color: #e74c3c;
            }
            
            .suitecrm-field-input {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 16px;
                transition: border-color 0.3s;
            }
            
            .suitecrm-field-input:focus {
                outline: none;
                border-color: #3498db;
            }
            
            .suitecrm-form-submit {
                background: #3498db;
                color: white;
                padding: 12px 30px;
                border: none;
                border-radius: 4px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.3s;
            }
            
            .suitecrm-form-submit:hover {
                background: #2980b9;
            }
            
            .suitecrm-form-submit:disabled {
                background: #95a5a6;
                cursor: not-allowed;
            }
            
            .suitecrm-loading {
                text-align: center;
                padding: 40px;
                color: #666;
            }
            
            .suitecrm-error {
                text-align: center;
                padding: 20px;
                color: #e74c3c;
                background: #fee;
                border-radius: 4px;
            }
            
            .suitecrm-error-message {
                background: #fee;
                color: #e74c3c;
                padding: 10px;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            
            .suitecrm-success {
                text-align: center;
                padding: 40px;
            }
            
            .suitecrm-success .success-icon {
                color: #27ae60;
                margin-bottom: 20px;
            }
            
            .suitecrm-success h3 {
                color: #27ae60;
                margin-bottom: 10px;
            }
            
            .suitecrm-success p {
                color: #666;
            }
        `;
        
        document.head.appendChild(style);
    }
    
})();