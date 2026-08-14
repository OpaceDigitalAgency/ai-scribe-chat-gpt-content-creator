/**
 * Modal View
 * Handles modal display and interactions
 */
class ModalView {
    constructor(appState) {
        this.appState = appState;

        this.setupEventListeners();
        this.updateCostCalculator();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Modal controls
        document.addEventListener('click', (e) => {
            if (e.target.matches('.modal-overlay') || e.target.matches('.modal-close')) {
                this.closeModal();
            }
        });

        // Cost calculator model selection
        const modelSelect = document.getElementById('model-select');
        if (modelSelect) {
            modelSelect.addEventListener('change', () => {
                this.updateCostCalculator();
            });
        }
    }

    /**
     * Open modal
     */
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');

            if (modalId === 'cost-calculator-modal') {
                this.updateCostCalculator();
            }
        }
    }

    /**
     * Close modal
     */
    closeModal() {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }

    /**
     * Update cost calculator display
     */
    updateCostCalculator() {
        const stepCostEl = document.getElementById('step-cost');
        const totalCostEl = document.getElementById('total-cost');
        const tokenEstimateEl = document.getElementById('token-estimate');

        if (stepCostEl) stepCostEl.textContent = '$0.00';
        if (totalCostEl) totalCostEl.textContent = '$0.00';
        if (tokenEstimateEl) tokenEstimateEl.textContent = '0';
    }

    /**
     * Render modal content
     */
    render(modalId, data) {
        // Implementation for rendering modal content
        console.log(`Rendering modal ${modalId}`, data);
    }
}