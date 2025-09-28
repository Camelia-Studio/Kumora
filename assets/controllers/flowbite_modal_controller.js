import { Controller } from "@hotwired/stimulus"
import { Modal } from "flowbite"

export default class extends Controller {
    static targets = ["title", "message", "icon"]
    static values = { type: String }

    // Variables pour stocker les callbacks et l'instance Flowbite
    confirmCallback = null
    cancelCallback = null
    modalInstance = null

    connect() {
        // Initialiser la modale Flowbite
        this.modalInstance = new Modal(this.element, {
            backdrop: 'static',
            backdropClasses: 'bg-gray-900/50 dark:bg-gray-900/80 fixed inset-0 z-40',
            closable: false
        })

        // Écouter les événements personnalisés pour afficher la modale
        this.element.addEventListener('modal:show', (e) => {
            const { title, message, type } = e.detail
            this.show(title, message, { type })
        })

        // Fermer la modale en cliquant sur l'arrière-plan
        this.element.addEventListener('click', (e) => {
            if (e.target === this.element) {
                this.close()
            }
        })
    }

    disconnect() {
        if (this.modalInstance) {
            this.modalInstance.destroy()
        }
    }

    show(title, message, options = {}) {
        this.titleTarget.textContent = title
        this.messageTarget.textContent = message

        // Pour les modales de notification, gérer les différents types d'icônes
        if (this.typeValue === 'notification' && this.hasIconTarget) {
            this.updateIconForType(options.type || 'success')
        }

        // Pour les confirmations, configurer les callbacks
        if (this.typeValue === 'confirmation') {
            this.confirmCallback = () => {
                if (this.element._resolvePromise) {
                    this.element._resolvePromise(true)
                    this.element._resolvePromise = null
                }
            }
            this.cancelCallback = () => {
                if (this.element._resolvePromise) {
                    this.element._resolvePromise(false)
                    this.element._resolvePromise = null
                }
            }
        }

        // Afficher la modale avec Flowbite
        this.modalInstance.show()
    }

    updateIconForType(type) {
        const iconContainer = this.iconTarget

        if (type === 'success') {
            iconContainer.className = 'flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-br from-green-100 to-green-50 dark:from-green-900/20 dark:to-green-800/10 mb-4 shadow-lg'
            iconContainer.innerHTML = '<svg class="h-8 w-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
        } else if (type === 'error') {
            iconContainer.className = 'flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-br from-red-100 to-red-50 dark:from-red-900/20 dark:to-red-800/10 mb-4 shadow-lg'
            iconContainer.innerHTML = '<svg class="h-8 w-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"></path></svg>'
        } else if (type === 'warning') {
            iconContainer.className = 'flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-br from-amber-100 to-amber-50 dark:from-amber-900/20 dark:to-amber-800/10 mb-4 shadow-lg'
            iconContainer.innerHTML = '<svg class="h-8 w-8 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>'
        } else if (type === 'info') {
            iconContainer.className = 'flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-br from-blue-100 to-blue-50 dark:from-blue-900/20 dark:to-blue-800/10 mb-4 shadow-lg'
            iconContainer.innerHTML = '<svg class="h-8 w-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
        }
    }

    close() {
        this.modalInstance.hide()

        if (this.cancelCallback) {
            this.cancelCallback()
        } else if (this.element._resolvePromise) {
            // Si on ferme sans callback, on considère ça comme un cancel
            this.element._resolvePromise(false)
            this.element._resolvePromise = null
        }

        this.cleanupCallbacks()
    }

    confirm() {
        this.modalInstance.hide()

        if (this.confirmCallback) {
            this.confirmCallback()
        }

        this.cleanupCallbacks()
    }

    cancel() {
        this.close()
    }

    cleanupCallbacks() {
        this.confirmCallback = null
        this.cancelCallback = null
    }
}

// Fonctions globales pour faciliter l'utilisation
window.customConfirm = function(message, title = 'Confirmer l\'action') {
    const confirmationModal = document.getElementById('confirmationModal')

    if (!confirmationModal) {
        console.error('confirmationModal not found!')
        return Promise.resolve(false)
    }

    // Stocker la promesse AVANT de déclencher l'événement
    return new Promise((resolve) => {
        confirmationModal._resolvePromise = resolve

        // Déclencher l'événement pour afficher la modale
        const event = new CustomEvent('modal:show', {
            detail: { title, message }
        })
        confirmationModal.dispatchEvent(event)
    })
}

window.customAlert = function(message, title = 'Notification', type = 'success') {
    const notificationModal = document.getElementById('notificationModal')

    if (!notificationModal) {
        console.error('notificationModal not found!')
        return Promise.resolve()
    }

    // Déclencher l'événement pour afficher la modale
    const event = new CustomEvent('modal:show', {
        detail: { title, message, type }
    })
    notificationModal.dispatchEvent(event)

    return Promise.resolve()
}