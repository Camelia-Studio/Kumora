import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        files: Array
    }

    confirmDelete() {
        const fileNames = this.filesValue.map(path => path.split('/').pop());
        const fileList = fileNames.map(name => `• ${name}`).join('<br>');
        const message = `Voulez-vous vraiment supprimer ces ${this.filesValue.length} élément(s) ?<br><br><strong>Fichiers à supprimer :</strong><br>${fileList}`;

        customConfirm(message, 'Suppression en masse').then(confirmed => {
            if (confirmed) {
                this.element.submit();
            }
        });
    }
}
