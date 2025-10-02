import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import 'flowbite';

// Gestionnaire pour la suppression en masse
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        if (e.target.closest('.bulk-delete-btn')) {
            const btn = e.target.closest('.bulk-delete-btn');
            const selectedFiles = JSON.parse(btn.dataset.files);
            const fileNames = selectedFiles.map(path => path.split('/').pop());
            const fileList = fileNames.map(name => `• ${name}`).join('<br>');
            const message = `Voulez-vous vraiment supprimer ces ${selectedFiles.length} élément(s) ?<br><br><strong>Fichiers à supprimer :</strong><br>${fileList}`;

            customConfirm(message, 'Suppression en masse').then(confirmed => {
                if (confirmed) {
                    document.getElementById('bulkDeleteForm').submit();
                }
            });
        }
    });
});
