<?php
// config.php
return [
    'users' => [
        'admin' => [
            'password' => 'votre_mot_de_passe_admin',  // À changer !
            'description' => 'Administrateur'
        ],
        'user1' => [
            'password' => 'votre_mot_de_passe_user1',  // À changer !
            'description' => 'Utilisateur 1'
        ],
        'invite' => [
            'password' => 'votre_mot_de_passe_invite',  // À changer !
            'description' => 'Invité'
        ]
    ],
    'session_duration' => 3600, // Durée de la session en secondes (1 heure)
    // Vous pouvez ajouter autant d'utilisateurs que nécessaire
];
?>
