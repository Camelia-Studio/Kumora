import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["permissionsContainer", "showBlock"]
    static values = {
        lowestRoleId: String
    }

    connect() {
        console.log('Directory permissions controller connected')
    }

    addPermission() {
        const container = this.permissionsContainerTarget
        const prototype = container.dataset.prototype
        const index = container.children.length

        const newPermission = prototype.replace(/__name__/g, index)
        container.insertAdjacentHTML('beforeend', newPermission)

        // Ajouter un écouteur pour le nouveau champ de rôle
        const newRoleSelect = container.querySelector(`[id$="_${index}_role"]`)
        if (newRoleSelect) {
            newRoleSelect.addEventListener('change', (e) => {
                this.toggleWriteField(e.target, index)
            })
        }
    }

    togglePermissions(event) {
        const value = event.target.value
        console.log('Toggle permissions, value:', value)
        if (value === 'shared') {
            this.showBlockTarget.classList.remove('hidden')
        } else {
            this.showBlockTarget.classList.add('hidden')
        }
    }

    toggleWriteField(selectElement, index) {
        const value = selectElement.value
        const writeField = this.permissionsContainerTarget.querySelector(`[id$="_${index}_write"]`)

        if (writeField) {
            const writeFieldContainer = writeField.parentElement.parentElement
            if (value === this.lowestRoleIdValue) {
                writeFieldContainer.classList.add('hidden')
            } else {
                writeFieldContainer.classList.remove('hidden')
            }
        }
    }
}
