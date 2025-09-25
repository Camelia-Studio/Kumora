# 🌤️ Kumora

**Le disque nuagique de l'association** - Une plateforme de partage de fichiers moderne et sécurisée pour les membres d'associations.

*Développé avec ❤️ par la branche CILA de l'association Camélia Studio*

## 📋 Table des matières

- [À propos](#-à-propos)
- [Fonctionnalités](#-fonctionnalités)
- [Technologies](#-technologies)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Utilisation](#-utilisation)
- [Architecture](#-architecture)
- [Contribution](#-contribution)

## 🚀 À propos

Kumora est une application web de stockage et de partage de fichiers conçue spécialement pour les associations. Elle permet aux membres de l'association d'échanger des fichiers en toute simplicité avec un système de permissions granulaire basé sur les groupes d'accès.

### Pourquoi Kumora ?

- **Sécurité** : Système d'authentification robuste et gestion des permissions par groupes
- **Simplicité** : Interface intuitive et responsive, optimisée mobile
- **Flexibilité** : Système de groupes d'accès configurable selon les besoins de l'association
- **Moderne** : Basé sur Symfony 7.2 avec les dernières technologies web

## ✨ Fonctionnalités

### 🔐 Gestion des utilisateurs
- Authentification sécurisée avec système de connexion
- Profils utilisateurs avec suivi de dernière connexion
- Réinitialisation de mot de passe par email
- Interface d'administration pour la gestion des membres

### 👥 Système de groupes d'accès
- Création et gestion de groupes d'accès personnalisés
- Permissions granulaires par dossier
- Hiérarchie des permissions avec héritage
- Icônes personnalisables pour chaque groupe

### 📁 Gestion de fichiers avancée
- **Navigation** : Arborescence de dossiers intuitive avec fil d'Ariane
- **Upload** : Interface de glisser-déposer pour l'ajout de fichiers
- **Prévisualisation** : Aperçu des fichiers directement dans le navigateur
- **Actions** : Renommer, déplacer, supprimer fichiers et dossiers
- **Partage** : Liens de partage avec copie en un clic
- **Téléchargement** : Accès direct aux fichiers

### 📱 Interface responsive
- **Design adaptatif** : Interface optimisée pour desktop et mobile
- **Mode sombre** : Support automatique du thème sombre
- **Vues hybrides** : Tableaux pour desktop, cartes pour mobile
- **Navigation tactile** : Optimisée pour les appareils tactiles

### ⚡ Performance et UX
- **Composants Live** : Mise à jour en temps réel avec Symfony UX
- **Navigation fluide** : Turbo pour une navigation sans rechargement
- **Icônes** : Bibliothèque d'icônes FontAwesome et Material Design
- **Styling** : Interface moderne avec Tailwind CSS et Flowbite

## 🛠️ Technologies

### Backend
- **Framework** : Symfony 7.2
- **PHP** : Version 8.2+
- **Base de données** : SQLite (configurable pour PostgreSQL/MySQL)
- **ORM** : Doctrine ORM 3.5
- **Stockage** : League Flysystem 3.x

### Frontend
- **Templating** : Twig 3.x
- **CSS Framework** : Tailwind CSS
- **Composants UI** : Flowbite
- **JavaScript** : Symfony UX (Stimulus, Turbo, Live Components)
- **Icônes** : Symfony UX Icons

### Outils de développement
- **Qualité de code** : PHP-CS-Fixer, PHPStan, Rector
- **Tests** : PHPUnit
- **Assets** : Symfony AssetMapper

## 📦 Installation

### Prérequis
- PHP 8.2 ou supérieur
- Composer
- Node.js (pour Tailwind CSS)
- CLI Symfony

### Étapes d'installation

1. **Cloner le projet**
```bash
git clone https://github.com/votre-organisation/kumora.git
cd kumora
```

2. **Installer les dépendances PHP**
```bash
composer install
```

3. **Configuration de l'environnement**
```bash
cp .env .env.local
# Éditer .env.local avec vos paramètres
```

4. **Initialiser la base de données**
```bash
php bin/console doctrine:migrations:migrate
```

5. **Compiler les assets**
```bash
php bin/console tailwind:build
php bin/console asset-map:compile
```

6. **Lancer le serveur de développement**
```bash
symfony serve
```

## ⚙️ Configuration

### Variables d'environnement principales

```env
# Base de données
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"

# Mailer (pour réinitialisation mot de passe)
MAILER_DSN=smtp://localhost:1025

# Configuration stockage fichiers
FLYSYSTEM_ADAPTER=local
UPLOAD_DIRECTORY=%kernel.project_dir%/var/uploads
```

### Configuration des groupes d'accès

1. Accéder à l'interface admin : `/admin/access-groups`
2. Créer les groupes selon votre organisation
3. Définir les permissions par dossier
4. Assigner les utilisateurs aux groupes appropriés

## 🎯 Utilisation

### Pour les utilisateurs

1. **Connexion** : Se connecter avec les identifiants fournis par l'administrateur
2. **Navigation** : Parcourir les dossiers selon vos permissions
3. **Upload** : Glisser-déposer des fichiers dans les dossiers autorisés
4. **Partage** : Copier les liens de partage des fichiers
5. **Gestion** : Renommer, déplacer ou supprimer vos fichiers

### Pour les administrateurs

1. **Gestion utilisateurs** : `/admin/users` - Créer, modifier, supprimer des comptes
2. **Groupes d'accès** : `/admin/access-groups` - Configurer les permissions
3. **Surveillance** : Suivre les dernières connexions des utilisateurs
4. **Permissions** : Définir les accès par dossier et par groupe

## 🏗️ Architecture

### Structure des dossiers
```
src/
├── Controller/          # Contrôleurs MVC
├── Entity/             # Entités Doctrine
├── Form/               # Formulaires Symfony
├── Repository/         # Repositories Doctrine  
├── Security/           # Système d'authentification
├── Service/            # Services métier
├── Twig/              # Extensions Twig personnalisées
└── EventListener/     # Écouteurs d'événements

templates/
├── admin/             # Templates administration
├── components/        # Composants Twig réutilisables
├── partials/          # Éléments partiels
└── security/          # Templates authentification
```

### Entités principales

- **User** : Utilisateurs avec groupes d'accès et suivi de connexion
- **AccessGroup** : Groupes d'accès avec icônes personnalisables  
- **ParentDirectory** : Dossiers racine avec permissions
- **ParentDirectoryPermission** : Permissions granulaires par dossier

### Système de permissions

1. **Permissions par groupe** : Chaque groupe a des droits sur certains dossiers
2. **Héritage** : Les sous-dossiers héritent des permissions du parent
3. **Lecture/Écriture** : Permissions distinctes pour consulter et modifier
4. **Isolation** : Les utilisateurs ne voient que leurs dossiers autorisés

## 🤝 Contribution

### Standards de code

- **PSR-12** : Standard de codage PHP respecté
- **Symfony Best Practices** : Architecture respectueuse des conventions
- **Tests** : Code couvert par des tests unitaires et fonctionnels

### Processus de contribution

1. Fork du projet
2. Créer une branche feature (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Commit des changements (`git commit -am 'Ajout nouvelle fonctionnalité'`)
4. Push de la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Créer une Pull Request

### Outils de développement

```bash
# Analyse statique
composer phpstan

# Correction du style de code  
composer cs-fix

# Refactoring automatique
composer rector

# Tests
composer test
```

---