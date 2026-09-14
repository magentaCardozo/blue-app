-- ============================================================
-- INDEX DE PERFORMANCE — Projet Blue App
-- Basé sur l'analyse des requêtes Installation & Contrôle
-- Exécuter dans phpMyAdmin ou en ligne de commande MySQL
-- ============================================================

-- ============================================================
-- 1. t_log_installation (table principale des installations)
-- ============================================================
-- JOIN principal: ref_identific = t_main_data.id_
ALTER TABLE t_log_installation ADD INDEX idx_li_ref_identific (ref_identific);

-- JOINs sur les utilisateurs
ALTER TABLE t_log_installation ADD INDEX idx_li_code_installateur (code_installateur);
ALTER TABLE t_log_installation ADD INDEX idx_li_chef_equipe (chef_equipe);

-- Filtres fréquents
ALTER TABLE t_log_installation ADD INDEX idx_li_annule (annule);
ALTER TABLE t_log_installation ADD INDEX idx_li_date_fin (date_fin_installation);
ALTER TABLE t_log_installation ADD INDEX idx_li_statut (statut_installation);
ALTER TABLE t_log_installation ADD INDEX idx_li_type_install (type_installation);
ALTER TABLE t_log_installation ADD INDEX idx_li_id_equipe (id_equipe);

-- Composite: annule + date (très utilisé pour le tri et filtrage)
ALTER TABLE t_log_installation ADD INDEX idx_li_annule_date (annule, date_fin_installation);

-- ============================================================
-- 2. t_log_controle (table principale des contrôles)
-- ============================================================
-- JOIN principal: ref_fiche_identification = t_main_data.id_
ALTER TABLE t_log_controle ADD INDEX idx_lc_ref_identif (ref_fiche_identification);

-- JOIN sur le contrôleur
ALTER TABLE t_log_controle ADD INDEX idx_lc_controleur (controleur);

-- Filtres fréquents
ALTER TABLE t_log_controle ADD INDEX idx_lc_annule (annule);
ALTER TABLE t_log_controle ADD INDEX idx_lc_date_controle (date_controle);
ALTER TABLE t_log_controle ADD INDEX idx_lc_cas_fraude (cas_de_fraude);

-- Composite: annule + date (très utilisé)
ALTER TABLE t_log_controle ADD INDEX idx_lc_annule_date (annule, date_controle);

-- ============================================================
-- 3. t_main_data (table centrale des identifications/clients)
-- ============================================================
-- JOIN sur cvs_id
ALTER TABLE t_main_data ADD INDEX idx_md_cvs_id (cvs_id);

-- JOIN sur adresse_id
ALTER TABLE t_main_data ADD INDEX idx_md_adresse_id (adresse_id);

-- JOIN sur organisme
ALTER TABLE t_main_data ADD INDEX idx_md_id_equipe (id_equipe_identification);

-- JOIN sur client
ALTER TABLE t_main_data ADD INDEX idx_md_client_id (client_id);

-- JOIN sur identificateur
ALTER TABLE t_main_data ADD INDEX idx_md_identificateur (identificateur);

-- JOIN sur chef_equipe
ALTER TABLE t_main_data ADD INDEX idx_md_chef_equipe (chef_equipe);

-- Filtres fréquents
ALTER TABLE t_main_data ADD INDEX idx_md_annule (annule);
ALTER TABLE t_main_data ADD INDEX idx_md_is_draft (is_draft);
ALTER TABLE t_main_data ADD INDEX idx_md_est_installer (est_installer);

-- Composite: annule + date_identification (pour les recherches par date)
ALTER TABLE t_main_data ADD INDEX idx_md_annule_date (annule, date_identification);

-- ============================================================
-- 4. t_log_adresses (table des adresses)
-- ============================================================
-- JOIN sur ville_id, commune_id, quartier_id, avenue
ALTER TABLE t_log_adresses ADD INDEX idx_adr_ville (ville_id);
ALTER TABLE t_log_adresses ADD INDEX idx_adr_commune (commune_id);
ALTER TABLE t_log_adresses ADD INDEX idx_adr_quartier (quartier_id);
ALTER TABLE t_log_adresses ADD INDEX idx_adr_avenue (avenue);

-- ============================================================
-- 5. t_param_adresse_entity (table des entités géographiques)
-- ============================================================
-- Déjà clé primaire sur `code`, mais category_id est filtré
ALTER TABLE t_param_adresse_entity ADD INDEX idx_pae_category (category_id);

-- ============================================================
-- 6. t_log_installation_users (installateurs supplémentaires)
-- ============================================================
-- JOIN et filtre sur ref_inst_
ALTER TABLE t_log_installation_users ADD INDEX idx_liu_ref_inst (ref_inst_);

-- JOIN sur ref_user
ALTER TABLE t_log_installation_users ADD INDEX idx_liu_ref_user (ref_user);

-- ============================================================
-- 7. t_utilisateurs (utilisateurs)
-- ============================================================
-- Déjà clé primaire sur code_utilisateur, mais id_group est utilisé
ALTER TABLE t_utilisateurs ADD INDEX idx_u_id_group (id_group);

-- ============================================================
-- 8. t_param_organisme (organismes)
-- ============================================================
-- Déjà clé primaire sur ref_organisme, pas d'index supplémentaire nécessaire

-- ============================================================
-- 9. t_param_cvs (CVS)
-- ============================================================
-- Déjà clé primaire sur `code`, pas d'index supplémentaire nécessaire

-- ============================================================
-- 10. Tables paramétriques (petites tables, pas besoin d'index)
-- ============================================================
-- t_param_statut_installation, t_param_motif_en_cours, etc.
-- sont trop petites pour nécessiter des index

-- ============================================================
-- VÉRIFICATION (optionnel)
-- ============================================================
-- Pour vérifier les index créés :
-- SHOW INDEX FROM t_log_installation;
-- SHOW INDEX FROM t_log_controle;
-- SHOW INDEX FROM t_main_data;
-- SHOW INDEX FROM t_log_adresses;
-- SHOW INDEX FROM t_log_installation_users;
