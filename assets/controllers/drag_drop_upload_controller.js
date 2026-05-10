import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["dropzone", "overlay", "fileInput"]
    static values = {
        baseUrl: String
    }

    connect() {
        console.log('Drag & drop upload controller connected')
        console.log('Base URL:', this.baseUrlValue)

        // Compteur pour suivre les dragenter/dragleave
        this.dragCounter = 0

        // Sauvegarder le path actuel depuis l'URL
        this.lastPath = this.getPathFromUrl()
        console.log('Initial path from URL:', this.lastPath)

        // Initialiser les event listeners
        this.setupEventListeners()

        // Écouter les changements d'URL (beaucoup plus fiable)
        // Attendre un peu avant de démarrer le polling pour éviter les faux positifs au chargement
        console.log('Setting up URL monitoring via polling')
        setTimeout(() => {
            this.pollingInterval = setInterval(() => {
                const currentPath = this.getPathFromUrl()
                if (currentPath !== this.lastPath) {
                    console.log('URL change detected: path changed from', this.lastPath, 'to', currentPath)
                    this.handlePathChange(currentPath)
                }
            }, 200) // Vérifier toutes les 200ms
        }, 500) // Attendre 500ms avant de démarrer le polling
    }

    handlePathChange(newPath) {
        console.log('Path changed from', this.lastPath, 'to', newPath)
        this.lastPath = newPath

        // Nettoyer les anciens listeners
        this.removeEventListeners()

        // Réinitialiser le drag counter et masquer l'overlay
        this.dragCounter = 0
        if (this.hasOverlayTarget) {
            this.overlayTarget.classList.add('hidden')
        }

        // Reconfigurer les listeners
        this.setupEventListeners()
    }

    getPathFromUrl() {
        // Récupérer le path depuis les query params de l'URL
        const urlParams = new URLSearchParams(window.location.search)
        return urlParams.get('path') || ''
    }

    getCurrentPath() {
        // Utiliser getPathFromUrl comme source de vérité
        return this.getPathFromUrl()
    }

    getUploadUrl() {
        const currentPath = this.getCurrentPath()
        if (!currentPath) return ''

        return this.baseUrlValue.replace('__PATH__', encodeURIComponent(currentPath))
    }

    setupEventListeners() {
        const currentPath = this.getCurrentPath()

        // Vérifier si le controller est activé (path non vide)
        if (!currentPath || currentPath === '') {
            console.log('Controller disabled (empty path), skipping event listeners')
            return
        }

        // Vérifier que les targets existent
        if (!this.hasDropzoneTarget || !this.hasOverlayTarget) {
            console.error('Required targets not found', {
                hasDropzone: this.hasDropzoneTarget,
                hasOverlay: this.hasOverlayTarget
            })
            return
        }

        console.log('Setting up event listeners for path:', currentPath)
        console.log('Upload URL will be:', this.getUploadUrl())

        try {
            // Lier les méthodes au contexte
            this.boundPreventDefaults = this.preventDefaults.bind(this)
            this.boundHandleDragEnter = this.handleDragEnter.bind(this)
            this.boundHandleDragLeave = this.handleDragLeave.bind(this)
            this.boundHandleDrop = this.handleDrop.bind(this)

            // Prévenir le comportement par défaut du navigateur pour toute la fenêtre
            const events = ['dragenter', 'dragover', 'dragleave', 'drop']
            events.forEach(eventName => {
                document.body.addEventListener(eventName, this.boundPreventDefaults, false)
            })

            // Gérer le drag enter au niveau du body
            document.body.addEventListener('dragenter', this.boundHandleDragEnter, false)

            // Gérer le drag leave au niveau du body
            document.body.addEventListener('dragleave', this.boundHandleDragLeave, false)

            // Gérer le drop
            document.body.addEventListener('drop', this.boundHandleDrop, false)

            console.log('Drag & drop controller initialized successfully')
        } catch (error) {
            console.error('Error in drag-drop-upload connect:', error)
        }
    }

    removeEventListeners() {
        console.log('Attempting to remove event listeners', {
            hasPreventDefaults: !!this.boundPreventDefaults,
            hasDragEnter: !!this.boundHandleDragEnter,
            hasDragLeave: !!this.boundHandleDragLeave,
            hasDrop: !!this.boundHandleDrop
        })

        // Vérifier que les handlers existent avant de tenter de les retirer
        if (!this.boundPreventDefaults &&
            !this.boundHandleDragEnter &&
            !this.boundHandleDragLeave &&
            !this.boundHandleDrop) {
            console.log('No event listeners to remove')
            return
        }

        console.log('Removing event listeners')

        // Nettoyer les event listeners seulement s'ils existent
        if (this.boundPreventDefaults) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                document.body.removeEventListener(eventName, this.boundPreventDefaults, false)
            })
        }

        if (this.boundHandleDragEnter) {
            document.body.removeEventListener('dragenter', this.boundHandleDragEnter, false)
        }
        if (this.boundHandleDragLeave) {
            document.body.removeEventListener('dragleave', this.boundHandleDragLeave, false)
        }
        if (this.boundHandleDrop) {
            document.body.removeEventListener('drop', this.boundHandleDrop, false)
        }

        console.log('Event listeners removed successfully')
    }

    disconnect() {
        // Nettoyer l'intervalle de polling
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval)
        }

        // Nettoyer les event listeners du drag & drop
        this.removeEventListeners()
    }

    preventDefaults(e) {
        e.preventDefault()
        e.stopPropagation()
    }

    handleDragEnter(e) {
        const currentPath = this.getCurrentPath()
        if (!currentPath || currentPath === '' || !this.hasOverlayTarget) return

        this.dragCounter++
        if (this.dragCounter === 1) {
            this.overlayTarget.classList.remove('hidden')
        }
    }

    handleDragLeave(e) {
        const currentPath = this.getCurrentPath()
        if (!currentPath || currentPath === '' || !this.hasOverlayTarget) return

        this.dragCounter--
        if (this.dragCounter === 0) {
            this.overlayTarget.classList.add('hidden')
        }
    }

    async handleDrop(e) {
        const currentPath = this.getCurrentPath()
        if (!currentPath || currentPath === '') return

        // Réinitialiser le compteur et masquer l'overlay
        this.dragCounter = 0
        if (this.hasOverlayTarget) {
            this.overlayTarget.classList.add('hidden')
        }

        const dt = e.dataTransfer
        const items = dt.items

        if (items) {
            // Utiliser l'API DataTransferItem pour supporter les dossiers
            const files = await this.getAllFileEntries(items)
            this.uploadFiles(files)
        } else {
            // Fallback pour les navigateurs qui ne supportent pas DataTransferItem
            this.uploadFiles(dt.files)
        }
    }

    async getAllFileEntries(dataTransferItems) {
        const files = []
        const entries = []

        // Convertir les items en entries
        for (let i = 0; i < dataTransferItems.length; i++) {
            const item = dataTransferItems[i]
            if (item.kind === 'file') {
                // Essayer d'obtenir l'entry pour supporter les dossiers
                const entry = item.webkitGetAsEntry ? item.webkitGetAsEntry() : null
                if (entry) {
                    entries.push(entry)
                } else {
                    // Fallback : si webkitGetAsEntry n'est pas disponible, utiliser getAsFile
                    const file = item.getAsFile()
                    if (file) {
                        file.relativePath = file.name
                        files.push(file)
                    }
                }
            }
        }

        // Parcourir récursivement les entries
        for (const entry of entries) {
            const entryFiles = await this.readEntry(entry)
            files.push(...entryFiles)
        }

        return files
    }

    async readEntry(entry, path = '') {
        if (entry.isFile) {
            return new Promise((resolve) => {
                entry.file((file) => {
                    // Ajouter le chemin relatif au fichier
                    file.relativePath = path + file.name
                    resolve([file])
                })
            })
        } else if (entry.isDirectory) {
            const dirReader = entry.createReader()
            const entries = await new Promise((resolve) => {
                dirReader.readEntries(resolve)
            })

            const files = []
            for (const childEntry of entries) {
                const childFiles = await this.readEntry(childEntry, path + entry.name + '/')
                files.push(...childFiles)
            }
            return files
        }
        return []
    }

    triggerFileInput() {
        this.fileInputTarget.click()
    }

    handleFileSelect(e) {
        const files = e.target.files
        this.uploadFiles(files)
    }

    async uploadFiles(files) {
        if (files.length === 0) return

        // Afficher un indicateur de chargement
        this.showLoading(files.length)

        const filesArray = Array.from(files)
        const totalFiles = filesArray.length
        let uploadedCount = 0
        let failedCount = 0

        // Uploader les fichiers par lots de 5
        const batchSize = 5
        for (let i = 0; i < filesArray.length; i += batchSize) {
            const batch = filesArray.slice(i, i + batchSize)

            try {
                await this.uploadBatch(batch)
                uploadedCount += batch.length
                this.updateLoadingProgress(uploadedCount, totalFiles)
            } catch (error) {
                console.error('Error uploading batch:', error)
                failedCount += batch.length
            }
        }

        // Masquer l'indicateur de chargement
        this.hideLoading()

        // Afficher le résultat et recharger après fermeture de la modale
        if (failedCount > 0) {
            this.showResultModal(
                `${uploadedCount} fichier(s) uploadé(s) avec succès, mais ${failedCount} échec(s).`,
                'Upload terminé avec des erreurs',
                'warning'
            )
        } else if (uploadedCount > 0) {
            this.showResultModal(
                `${uploadedCount} fichier(s) uploadé(s) avec succès.`,
                'Upload terminé',
                'success'
            )
        } else {
            // Pas de fichiers uploadés, recharger directement
            window.location.reload()
        }
    }

    showResultModal(message, title, type) {
        const notificationModal = document.getElementById('notificationModal')

        if (!notificationModal) {
            console.error('notificationModal not found!')
            window.location.reload()
            return
        }

        // Observer pour détecter quand la modale devient cachée
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'class') {
                    const classList = notificationModal.classList
                    if (classList.contains('hidden')) {
                        // La modale est cachée, recharger la page
                        observer.disconnect()
                        window.location.reload()
                    }
                }
            })
        })

        // Observer les changements de classe
        observer.observe(notificationModal, {
            attributes: true,
            attributeFilter: ['class']
        })

        // Déclencher l'événement pour afficher la modale
        const event = new CustomEvent('modal:show', {
            detail: { title, message, type }
        })
        notificationModal.dispatchEvent(event)
    }

    async uploadBatch(files) {
        const formData = new FormData()

        // Ajouter les fichiers du lot au FormData avec leurs chemins relatifs
        files.forEach((file) => {
            formData.append('upload[files][]', file)
            // Ajouter le chemin relatif si disponible
            if (file.relativePath) {
                formData.append('upload[paths][]', file.relativePath)
            } else {
                formData.append('upload[paths][]', file.name)
            }
        })

        // Envoyer la requête
        const uploadUrl = this.getUploadUrl()
        console.log('Uploading to:', uploadUrl)

        const response = await fetch(uploadUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })

        if (!response.ok) {
            throw new Error('Upload failed')
        }

        return response.json()
    }

    showLoading(fileCount) {
        // Créer un overlay de chargement
        const loadingOverlay = document.createElement('div')
        loadingOverlay.id = 'upload-loading'
        loadingOverlay.className = 'fixed inset-0 bg-gray-900/50 dark:bg-gray-900/80 flex items-center justify-center z-50'
        loadingOverlay.innerHTML = `
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-xl min-w-80">
                <div class="flex items-center gap-4 mb-4">
                    <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <div>
                        <p class="text-lg font-medium text-gray-900 dark:text-white">Upload en cours...</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400" id="upload-progress">0 / ${fileCount} fichier(s)</p>
                    </div>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                    <div id="upload-progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0"></div>
                </div>
            </div>
        `
        document.body.appendChild(loadingOverlay)
    }

    updateLoadingProgress(uploaded, total) {
        const progressText = document.getElementById('upload-progress')
        const progressBar = document.getElementById('upload-progress-bar')

        if (progressText) {
            progressText.textContent = `${uploaded} / ${total} fichier(s)`
        }

        if (progressBar) {
            const percentage = (uploaded / total) * 100
            progressBar.style.width = `${percentage}%`
        }
    }

    hideLoading() {
        const loadingOverlay = document.getElementById('upload-loading')
        if (loadingOverlay) {
            loadingOverlay.remove()
        }
    }
}
