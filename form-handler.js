/**
 * Navigator Broking - Form Handler with Mercury CRM Integration
 * Handles all contact form submissions across the website
 */

// Configuration
const FORM_CONFIG = {
    apiEndpoint: './mercury-integration.php', // Adjust path as needed
    successMessage: 'Thank you for your inquiry! We will contact you within 24 hours to discuss your mortgage needs.',
    errorMessage: 'Sorry, there was an error submitting your form. Please try again or call us directly at 0405678979.'
};

/**
 * Submit form data to Mercury CRM
 */
async function submitToMercury(formData, source) {
    const payload = {
        ...formData,
        source: source,
        timestamp: new Date().toISOString()
    };

    try {
        const response = await fetch(FORM_CONFIG.apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.error || 'Submission failed');
        }

        return result;
    } catch (error) {
        console.error('Mercury submission error:', error);
        throw error;
    }
}

/**
 * Show success/error messages
 */
function showMessage(element, message, isSuccess = true) {
    // Remove existing messages
    const existingMessage = element.querySelector('.form-message');
    if (existingMessage) {
        existingMessage.remove();
    }

    // Create message element
    const messageDiv = document.createElement('div');
    messageDiv.className = `form-message ${isSuccess ? 'success' : 'error'}`;
    messageDiv.innerHTML = `
        <div style="
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 6px;
            background: ${isSuccess ? '#d4edda' : '#f8d7da'};
            border: 1px solid ${isSuccess ? '#c3e6cb' : '#f5c6cb'};
            color: ${isSuccess ? '#155724' : '#721c24'};
        ">
            ${message}
        </div>
    `;
    
    element.appendChild(messageDiv);

    // Auto-remove after 10 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.remove();
        }
    }, 10000);
}

/**
 * Handle form submission
 */
async function handleFormSubmission(form, source) {
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton ? submitButton.textContent : '';
    
    try {
        // Disable submit button
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Submitting...';
        }

        // Collect form data
        const formData = new FormData(form);
        const data = {};
        
        // Convert FormData to object
        for (let [key, value] of formData.entries()) {
            data[key] = value.trim();
        }

        // Handle different form types
        if (source === 'medical-professionals') {
            data.specialization = form.querySelector('[name*="special"]')?.value || '';
        } else if (source === 'self-employed') {
            data.industry = form.querySelector('[name*="industry"]')?.value || '';
            data.yearsEmployed = form.querySelector('[name*="years"]')?.value || '';
        } else if (source === 'debt-consolidation') {
            data.debtAmount = form.querySelector('[name*="debt"]')?.value || '';
            data.debtTypes = form.querySelector('[name*="types"]')?.value || '';
        }

        // Submit to Mercury
        const result = await submitToMercury(data, source);
        
        // Show success message
        showMessage(form, result.message || FORM_CONFIG.successMessage, true);
        
        // Reset form
        form.reset();

        // Track success (optional - for analytics)
        if (typeof gtag !== 'undefined') {
            gtag('event', 'form_submit', {
                event_category: 'engagement',
                event_label: source
            });
        }

    } catch (error) {
        console.error('Form submission error:', error);
        showMessage(form, FORM_CONFIG.errorMessage, false);
    } finally {
        // Re-enable submit button
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalButtonText;
        }
    }
}

/**
 * Initialize form handlers when page loads
 */
document.addEventListener('DOMContentLoaded', function() {
    // Medical Professionals Form (03-medical-professionals.html)
    const medicalForm = document.querySelector('#medical-form, form[data-source="medical-professionals"]');
    if (medicalForm) {
        medicalForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmission(this, 'medical-professionals');
        });
    }

    // Self-Employed Form (04-self-employed.html)
    const selfEmployedForm = document.querySelector('#self-employed-form, form[data-source="self-employed"]');
    if (selfEmployedForm) {
        selfEmployedForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmission(this, 'self-employed');
        });
    }

    // Debt Consolidation Form (05-debt-consolidation.html)
    const debtForm = document.querySelector('#debt-form, form[data-source="debt-consolidation"]');
    if (debtForm) {
        debtForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmission(this, 'debt-consolidation');
        });
    }

    // Contact Form (09-contact.html)
    const contactForm = document.querySelector('#contactForm, form[data-source="contact"]');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmission(this, 'contact');
        });
    }

    // Generic form handler for any form with data-source attribute
    const allForms = document.querySelectorAll('form[data-source]');
    allForms.forEach(form => {
        const source = form.getAttribute('data-source');
        if (source && !form.hasAttribute('data-handler-attached')) {
            form.setAttribute('data-handler-attached', 'true');
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                handleFormSubmission(this, source);
            });
        }
    });
});

/**
 * Validation helpers
 */
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function validatePhone(phone) {
    const phoneRegex = /^[\d\s\-\+\(\)]{8,}$/;
    return phoneRegex.test(phone.replace(/\s/g, ''));
}

// Export functions for manual use if needed
window.NavigatorBroking = {
    submitToMercury,
    handleFormSubmission,
    validateEmail,
    validatePhone
};