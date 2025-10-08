import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        path: String,
        calculateUrl: String
    }

    connect() {
        // Charger la taille du dossier en asynchrone
        this.loadSize()
    }

    async loadSize() {
        try {
            // Afficher un spinner pendant le chargement
            this.element.innerHTML = '<svg class="animate-spin inline w-3 h-3 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>'

            const response = await fetch(`${this.calculateUrlValue}?path=${encodeURIComponent(this.pathValue)}`)
            const data = await response.json()

            if (data.success) {
                // Formater la taille
                const formattedSize = this.formatSize(data.size)
                this.element.textContent = formattedSize
            } else {
                this.element.textContent = '-'
            }
        } catch (error) {
            console.error('Error calculating folder size:', error)
            this.element.textContent = '-'
        }
    }

    formatSize(bytes) {
        if (bytes < 0) return '-'

        const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
        const factor = Math.floor((bytes.toString().length - 1) / 3)

        return (bytes / Math.pow(1024, factor)).toFixed(1) + ' ' + sizes[factor]
    }
}
