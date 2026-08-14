/**
 * Modal Controller
 * Manages modal interactions
 */
class ModalController {
    constructor(view, appState) {
        this.view = view;
        this.appState = appState;
    }

    /**
     * Open modal
     */
    openModal(modalId) {
        this.view.openModal(modalId);
        this.appState.setStateSlice('ui.activeModal', modalId);
    }

    /**
     * Close modal
     */
    closeModal() {
        this.view.closeModal();
        this.appState.setStateSlice('ui.activeModal', null);
    }
}