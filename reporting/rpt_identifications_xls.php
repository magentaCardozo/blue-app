<?php

require_once '../loader/init.php';

require_once '../classes/CLS_Reporting.php';
require_once '../classes/CVS.php';
require_once '../classes/MarqueCompteur.php';
require_once '../classes/AdresseEntity.php';
require_once '../classes/Site.php';
require_once '../classes/Organisme.php';
require_once '../classes/Droits.php';
require_once '../classes/Utils.php';
require_once '../classes/Utilisateur.php';
require_once '../classes/Database.php';
require_once '../classes/Cacher.php';
include_once '../core.php';
header('Content-type: text/html;charset=utf-8');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

$database = new Database();
$db = $database->getConnection();
$utilisateur = new Utilisateur($db);
$cls_report = new CLS_Reporting($db);
$cvs = new CVS($db);
$marquecompteur = new MarqueCompteur($db);
$adresseItem = new AdresseEntity($db);
$site_classe = new Site($db);
$organisme = new Organisme($db);

$liste_site = array();
$site = isset($_POST['site']) ? $_POST['site'] : NULL;
$du = isset($_POST['Du']) ? Utils::ClientToDbDateFormat($_POST['Du']) : "";
$au = isset($_POST['Au']) ? Utils::ClientToDbDateFormat($_POST['Au']) : "";
$du_ = isset($_POST['Du']) ? ($_POST['Du']) : "";
$au_ = isset($_POST['Au']) ? ($_POST['Au']) : "";
$utilisateur->is_logged_in();
$utilisateur->readOne();

try {
    if (in_array($MULTI_ACCESS_SITE_CODE, $site)) {
        $liste_site = $cls_report->GetAll_AccessibleUSerSite($utilisateur->code_utilisateur);
    } else {
        $liste_site = $site;
    }

    // Helper functions
    function cellAlign($newsheet, $cells, $align) {
        if ($align == "R") {
            $newsheet->getStyle($cells)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        } else if ($align == "J") {
            $newsheet->getStyle($cells)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_JUSTIFY);
        } else if ($align == "C") {
            $newsheet->getStyle($cells)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    function sanitizeSheetTitle($title, $maxLength = 31) {
        $invalidChars = ['\\', '/', '?', '*', '[', ']', ':'];
        $title = str_replace($invalidChars, '_', $title);
        $title = trim($title);
        if ($title === '') {
            $title = 'Sheet';
        }
        if (strlen($title) > $maxLength) {
            $title = substr($title, 0, $maxLength);
        }
        $title = trim($title, "'");
        if (is_numeric($title[0] ?? null)) {
            $title = '_' . $title;
        }
        return $title;
    }

    function cellBorder($newsheet, $cells) {
        $newsheet->getStyle($cells)->applyFromArray([
            'borders' => [
                'outline' => [
                    'style' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);
    }

    function cellStyle($newsheet, $cells, $size) {
        $newsheet->getStyle($cells)->getFont()->setSize($size);
        $newsheet->getStyle($cells)->getFont()->setBold(true);
    }

    function cellColor($newsheet, $cells, $color) {
        $newsheet->getStyle($cells)->getFill()->applyFromArray([
            'fillType' => Fill::FILL_SOLID,
            'startColor' => [
                'rgb' => $color,
            ],
        ]);
    }

    // ============= RÉCUPÉRATION DES DONNÉES =============

    // 1. Données globales - Sans filtre sur date_fin_identification
    $query_global = "SELECT 
        COUNT(*) as total_clients,
        COUNT(CASE WHEN num_compteur_actuel IS NOT NULL AND num_compteur_actuel != '' THEN 1 END) as total_compteurs,
        COUNT(CASE WHEN est_installer = 1 THEN 1 END) as compteurs_installes,
        COUNT(CASE WHEN est_desaffecter = 1 THEN 1 END) as compteurs_desaffectes,
        COUNT(CASE WHEN statut_client = '1' THEN 1 END) as clients_actifs,
        COUNT(CASE WHEN statut_client = '0' OR statut_client = '2' THEN 1 END) as clients_inactifs,
        COUNT(CASE WHEN num_compteur_actuel IS NULL OR num_compteur_actuel = '' THEN 1 END) as sans_numero_compteur,
        COUNT(CASE WHEN num_scelle_cpteur_un_install IS NULL OR num_scelle_cpteur_un_install = '' THEN 1 END) as sans_scelle,
        COUNT(CASE WHEN num_scelle_cpteur_un_install IS NOT NULL AND num_scelle_cpteur_un_install != '' THEN 1 END) as avec_scelle,
        COUNT(CASE WHEN marque_compteur_rpl IS NOT NULL AND marque_compteur_rpl != '' THEN 1 END) as avec_marque,
        COUNT(CASE WHEN (marque_compteur_rpl IS NULL OR marque_compteur_rpl = '') AND (marque_compteur_mecanique IS NULL OR marque_compteur_mecanique = '') THEN 1 END) as sans_marque,
        COUNT(CASE WHEN date_fin_identification IS NOT NULL THEN 1 END) as avec_date_identification,
        COUNT(CASE WHEN date_fin_identification IS NULL THEN 1 END) as sans_date_identification
    FROM t_main_data 
    WHERE annule = 0";

    $stmt = $db->prepare($query_global);
    $stmt->execute();
    $global_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si pas de données, on met des valeurs par défaut
    if (!$global_data) {
        $global_data = array(
            'total_clients' => 0,
            'total_compteurs' => 0,
            'compteurs_installes' => 0,
            'compteurs_desaffectes' => 0,
            'clients_actifs' => 0,
            'clients_inactifs' => 0,
            'sans_numero_compteur' => 0,
            'sans_scelle' => 0,
            'avec_scelle' => 0,
            'avec_marque' => 0,
            'sans_marque' => 0,
            'avec_date_identification' => 0,
            'sans_date_identification' => 0
        );
    }

    // 2. Données par CVS
    $cvs_data = array();
    if (!empty($liste_site)) {
        $first_site = $liste_site[0];
        $cvs_list = $cls_report->GetAll_Site_CVSList($first_site);
        
        foreach ($cvs_list as $cvs_item) {
            $query_cvs = "SELECT 
                COUNT(*) as clients_identifies,
                COUNT(CASE WHEN num_compteur_actuel IS NOT NULL AND num_compteur_actuel != '' THEN 1 END) as compteurs_identifies,
                COUNT(CASE WHEN est_installer = 1 THEN 1 END) as compteurs_installes,
                COUNT(CASE WHEN est_desaffecter = 1 THEN 1 END) as compteurs_desaffectes,
                COUNT(CASE WHEN statut_client = '1' THEN 1 END) as actifs,
                COUNT(CASE WHEN statut_client = '0' OR statut_client = '2' THEN 1 END) as inactifs,
                COUNT(CASE WHEN num_scelle_cpteur_un_install IS NOT NULL AND num_scelle_cpteur_un_install != '' THEN 1 END) as avec_scelle,
                COUNT(CASE WHEN num_scelle_cpteur_un_install IS NULL OR num_scelle_cpteur_un_install = '' THEN 1 END) as sans_scelle,
                COUNT(CASE WHEN num_compteur_actuel IS NULL OR num_compteur_actuel = '' THEN 1 END) as sans_numero
            FROM t_main_data 
            WHERE annule = 0 
            AND cvs_id = :cvs_id";
            
            $stmt_cvs = $db->prepare($query_cvs);
            $stmt_cvs->bindValue(":cvs_id", $cvs_item["code"]);
            $stmt_cvs->execute();
            $cvs_data[$cvs_item["libelle"]] = $stmt_cvs->fetch(PDO::FETCH_ASSOC);
        }
    }

    // 3. Données par équipe
    $query_equipe = "SELECT 
        t_param_organisme.denomination as equipe,
        COUNT(t_main_data.id_) as total_identifications,
        COUNT(CASE WHEN t_main_data.est_installer = 1 THEN 1 END) as installes
    FROM t_main_data
    INNER JOIN t_param_organisme ON t_main_data.id_equipe_identification = t_param_organisme.ref_organisme
    WHERE t_main_data.annule = 0
    GROUP BY t_main_data.id_equipe_identification
    ORDER BY total_identifications DESC";

    $stmt_equipe = $db->prepare($query_equipe);
    $stmt_equipe->execute();
    $equipe_data = $stmt_equipe->fetchAll(PDO::FETCH_ASSOC);

    // 4. Données par identificateur
    $query_identificateur = "SELECT 
        t_utilisateurs.nom_complet as identificateur,
        COUNT(t_main_data.id_) as total_identifications
    FROM t_main_data
    INNER JOIN t_utilisateurs ON t_main_data.identificateur = t_utilisateurs.code_utilisateur
    WHERE t_main_data.annule = 0
    GROUP BY t_main_data.identificateur
    ORDER BY total_identifications DESC
    LIMIT 10";

    $stmt_identificateur = $db->prepare($query_identificateur);
    $stmt_identificateur->execute();
    $identificateur_data = $stmt_identificateur->fetchAll(PDO::FETCH_ASSOC);

    // 5. Données par type client
    $query_type_client = "SELECT 
        type_client,
        COUNT(*) as total
    FROM t_main_data
    WHERE annule = 0 
    AND type_client IS NOT NULL
    GROUP BY type_client";

    $stmt_type_client = $db->prepare($query_type_client);
    $stmt_type_client->execute();
    $type_client_data = $stmt_type_client->fetchAll(PDO::FETCH_ASSOC);

    // 6. Données par marque
    $query_marque = "SELECT 
        marque_compteur_rpl as marque,
        COUNT(*) as total
    FROM t_main_data
    WHERE annule = 0 
    AND marque_compteur_rpl IS NOT NULL
    AND marque_compteur_rpl != ''
    GROUP BY marque_compteur_rpl
    ORDER BY total DESC
    LIMIT 10";

    $stmt_marque = $db->prepare($query_marque);
    $stmt_marque->execute();
    $marque_data = $stmt_marque->fetchAll(PDO::FETCH_ASSOC);

    // 7. Données par état de compteur
    $query_etat = "SELECT 
        etat_compteur as etat,
        COUNT(*) as total
    FROM t_main_data
    WHERE annule = 0 
    AND etat_compteur IS NOT NULL
    GROUP BY etat_compteur";

    $stmt_etat = $db->prepare($query_etat);
    $stmt_etat->execute();
    $etat_data = $stmt_etat->fetchAll(PDO::FETCH_ASSOC);

    // 8. Données par site
    $query_site = "SELECT 
        t_param_site_production.intitule_site as site,
        COUNT(t_main_data.id_) as clients,
        COUNT(CASE WHEN t_main_data.num_compteur_actuel IS NOT NULL AND t_main_data.num_compteur_actuel != '' THEN 1 END) as compteurs
    FROM t_main_data
    INNER JOIN t_param_site_production ON t_main_data.ref_site_identif = t_param_site_production.code_site
    WHERE t_main_data.annule = 0
    GROUP BY t_main_data.ref_site_identif";

    $stmt_site = $db->prepare($query_site);
    $stmt_site->execute();
    $site_data = $stmt_site->fetchAll(PDO::FETCH_ASSOC);

    // ============= CRÉATION DU TABLEAU DE BORD =============
    $objPHPExcel = new Spreadsheet();

    // ==================== FEUILLE 1 : TABLEAU DE BORD ====================
    $objPHPExcel->setActiveSheetIndex(0);
    $newsheet = $objPHPExcel->getActiveSheet();
    $newsheet->setTitle("TABLEAU DE BORD");

    // En-tête
    $rowNumber = 1;
    $newsheet->mergeCells('A1:L2');
    $newsheet->setCellValue('A1', 'TABLEAU DE BORD DES IDENTIFICATIONS');
    $style = $newsheet->getStyle('A1:L2');
    $style->getFont()->setBold(true)->setSize(16);
    $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    cellColor($newsheet, 'A1:L2', '4472C4');
    $newsheet->getStyle('A1:L2')->getFont()->getColor()->setARGB('FFFFFFFF');

    $rowNumber = 4;
    $newsheet->setCellValue('A4', 'Période : du ' . $du_ . ' au ' . $au_);
    cellStyle($newsheet, 'A4', 12);
    $newsheet->setCellValue('A5', 'Date d\'édition : ' . date('d/m/Y H:i:s'));
    cellStyle($newsheet, 'A5', 10);

    $rowNumber = 7;

    // Indicateurs clés
    $newsheet->mergeCells('A' . $rowNumber . ':B' . $rowNumber);
    cellStyle($newsheet, 'A' . $rowNumber, 14);
    $newsheet->setCellValue('A' . $rowNumber, 'INDICATEURS CLES');
    cellColor($newsheet, 'A' . $rowNumber . ':B' . $rowNumber, '4472C4');
    $newsheet->getStyle('A' . $rowNumber . ':B' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
    $rowNumber++;

    $total_clients = isset($global_data['total_clients']) ? $global_data['total_clients'] : 0;
    $total_compteurs = isset($global_data['total_compteurs']) ? $global_data['total_compteurs'] : 0;
    $compteurs_installes = isset($global_data['compteurs_installes']) ? $global_data['compteurs_installes'] : 0;
    $compteurs_desaffectes = isset($global_data['compteurs_desaffectes']) ? $global_data['compteurs_desaffectes'] : 0;

    $indicators = array(
        'Nombre total de clients' => $total_clients,
        'Nombre total de compteurs' => $total_compteurs,
        'Compteurs installés' => $compteurs_installes,
        'Compteurs désaffectés' => $compteurs_desaffectes,
        'Avec scellé' => isset($global_data['avec_scelle']) ? $global_data['avec_scelle'] : 0,
        'Sans scellé' => isset($global_data['sans_scelle']) ? $global_data['sans_scelle'] : 0,
        'Avec date d\'identification' => isset($global_data['avec_date_identification']) ? $global_data['avec_date_identification'] : 0,
        'Sans date d\'identification' => isset($global_data['sans_date_identification']) ? $global_data['sans_date_identification'] : 0
    );

    foreach ($indicators as $label => $value) {
        $newsheet->setCellValue('A' . $rowNumber, $label);
        $newsheet->setCellValue('B' . $rowNumber, $value);
        cellBorder($newsheet, 'A' . $rowNumber . ':B' . $rowNumber);
        $rowNumber++;
    }

    $rowNumber += 2;

    // État des compteurs
    $newsheet->mergeCells('D' . ($rowNumber - 6) . ':E' . ($rowNumber - 6));
    cellStyle($newsheet, 'D' . ($rowNumber - 6), 14);
    $newsheet->setCellValue('D' . ($rowNumber - 6), 'ETAT DES COMPTEURS');
    cellColor($newsheet, 'D' . ($rowNumber - 6) . ':E' . ($rowNumber - 6), '4472C4');
    $newsheet->getStyle('D' . ($rowNumber - 6) . ':E' . ($rowNumber - 6))->getFont()->getColor()->setARGB('FFFFFFFF');

    $rowNumber_temp = 8;
    $newsheet->setCellValue('D' . $rowNumber_temp, 'Etat');
    $newsheet->setCellValue('E' . $rowNumber_temp, 'Nombre');
    cellStyle($newsheet, 'D' . $rowNumber_temp . ':E' . $rowNumber_temp, 11);
    cellColor($newsheet, 'D' . $rowNumber_temp . ':E' . $rowNumber_temp, '4472C4');
    $newsheet->getStyle('D' . $rowNumber_temp . ':E' . $rowNumber_temp)->getFont()->getColor()->setARGB('FFFFFFFF');
    $rowNumber_temp++;

    $etats = array(
        'Installés' => $compteurs_installes,
        'Non installés' => $total_compteurs - $compteurs_installes,
        'Désaffectés' => $compteurs_desaffectes
    );

    foreach ($etats as $label => $value) {
        $newsheet->setCellValue('D' . $rowNumber_temp, $label);
        $newsheet->setCellValue('E' . $rowNumber_temp, $value);
        cellBorder($newsheet, 'D' . $rowNumber_temp . ':E' . $rowNumber_temp);
        $rowNumber_temp++;
    }

    // Qualité des données
    $rowNumber_temp += 2;
    $newsheet->mergeCells('D' . $rowNumber_temp . ':E' . $rowNumber_temp);
    cellStyle($newsheet, 'D' . $rowNumber_temp, 14);
    $newsheet->setCellValue('D' . $rowNumber_temp, 'QUALITE DES DONNEES');
    cellColor($newsheet, 'D' . $rowNumber_temp . ':E' . $rowNumber_temp, '4472C4');
    $newsheet->getStyle('D' . $rowNumber_temp . ':E' . $rowNumber_temp)->getFont()->getColor()->setARGB('FFFFFFFF');
    $rowNumber_temp++;

    $newsheet->setCellValue('D' . $rowNumber_temp, 'Information');
    $newsheet->setCellValue('E' . $rowNumber_temp, 'Manquante');
    cellStyle($newsheet, 'D' . $rowNumber_temp . ':E' . $rowNumber_temp, 10);
    cellColor($newsheet, 'D' . $rowNumber_temp . ':E' . $rowNumber_temp, '4472C4');
    $newsheet->getStyle('D' . $rowNumber_temp . ':E' . $rowNumber_temp)->getFont()->getColor()->setARGB('FFFFFFFF');
    $rowNumber_temp++;

    $qualite_data = array(
        'Numéro de compteur' => isset($global_data['sans_numero_compteur']) ? $global_data['sans_numero_compteur'] : 0,
        'Scellé' => isset($global_data['sans_scelle']) ? $global_data['sans_scelle'] : 0,
        'Marque du compteur' => isset($global_data['sans_marque']) ? $global_data['sans_marque'] : 0
    );

    foreach ($qualite_data as $label => $value) {
        $newsheet->setCellValue('D' . $rowNumber_temp, $label);
        $newsheet->setCellValue('E' . $rowNumber_temp, $value);
        cellBorder($newsheet, 'D' . $rowNumber_temp . ':E' . $rowNumber_temp);
        $rowNumber_temp++;
    }

    $newsheet->getColumnDimension('A')->setAutoSize(true);
    $newsheet->getColumnDimension('B')->setAutoSize(true);
    $newsheet->getColumnDimension('D')->setAutoSize(true);
    $newsheet->getColumnDimension('E')->setAutoSize(true);

    // ==================== FEUILLE 2 : SYNTHESE ====================
    $newsheet2 = $objPHPExcel->createSheet();
    $newsheet2->setTitle("SYNTHESE");

    $rowNumber = 1;
    $newsheet2->mergeCells('A' . $rowNumber . ':I' . $rowNumber);
    cellStyle($newsheet2, 'A' . $rowNumber, 14);
    $newsheet2->setCellValue('A' . $rowNumber, 'SYNTHESE PAR CVS - du ' . $du_ . ' au ' . $au_);
    cellColor($newsheet2, 'A' . $rowNumber . ':I' . $rowNumber, '4472C4');
    $newsheet2->getStyle('A' . $rowNumber . ':I' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
    $rowNumber += 2;

    // En-têtes
    $headers = array('Indicateur');
    $cvs_names = array_keys($cvs_data);
    $headers = array_merge($headers, $cvs_names);
    $headers[] = 'TOTAL';

    $col = 'A';
    foreach ($headers as $header) {
        $newsheet2->setCellValue($col . $rowNumber, $header);
        cellStyle($newsheet2, $col . $rowNumber, 10);
        cellColor($newsheet2, $col . $rowNumber, '4472C4');
        $newsheet2->getStyle($col . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
        $col++;
    }
    $rowNumber++;

    // Lignes de données
    $indicators_cvs = array(
        'Clients identifiés' => 'clients_identifies',
        'Compteurs identifiés' => 'compteurs_identifies',
        'Compteurs installés' => 'compteurs_installes',
        'Avec scellé' => 'avec_scelle',
        'Sans scellé' => 'sans_scelle',
        'Actifs' => 'actifs',
        'Inactifs' => 'inactifs',
        'Désaffectés' => 'compteurs_desaffectes'
    );

    foreach ($indicators_cvs as $label => $field) {
        $col = 'A';
        $newsheet2->setCellValue($col . $rowNumber, $label);
        cellBorder($newsheet2, $col . $rowNumber);
        $col++;

        $total = 0;
        foreach ($cvs_data as $cvs_name => $data) {
            $value = isset($data[$field]) ? $data[$field] : 0;
            $newsheet2->setCellValue($col . $rowNumber, $value);
            cellBorder($newsheet2, $col . $rowNumber);
            $total += $value;
            $col++;
        }

        $newsheet2->setCellValue($col . $rowNumber, $total);
        cellBorder($newsheet2, $col . $rowNumber);
        cellStyle($newsheet2, $col . $rowNumber, 10);
        $rowNumber++;
    }

    for ($i = 'A'; $i <= 'I'; $i++) {
        $newsheet2->getColumnDimension($i)->setAutoSize(true);
    }

    // ==================== FEUILLE 3 : COMPTEURS ====================
    $newsheet3 = $objPHPExcel->createSheet();
    $newsheet3->setTitle("COMPTEURS");

    $rowNumber = 1;
    $newsheet3->mergeCells('A' . $rowNumber . ':B' . $rowNumber);
    cellStyle($newsheet3, 'A' . $rowNumber, 14);
    $newsheet3->setCellValue('A' . $rowNumber, 'SYNTHESE DES COMPTEURS');
    cellColor($newsheet3, 'A' . $rowNumber . ':B' . $rowNumber, '4472C4');
    $newsheet3->getStyle('A' . $rowNumber . ':B' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
    $rowNumber += 2;

    $compteurs_synth = array(
        'Nombre total de compteurs' => $total_compteurs,
        'Compteurs installés' => $compteurs_installes,
        'Compteurs désaffectés' => $compteurs_desaffectes,
        'Avec scellé' => isset($global_data['avec_scelle']) ? $global_data['avec_scelle'] : 0,
        'Sans scellé' => isset($global_data['sans_scelle']) ? $global_data['sans_scelle'] : 0,
        'Avec marque' => isset($global_data['avec_marque']) ? $global_data['avec_marque'] : 0,
        'Sans marque' => isset($global_data['sans_marque']) ? $global_data['sans_marque'] : 0,
        'Sans numéro' => isset($global_data['sans_numero_compteur']) ? $global_data['sans_numero_compteur'] : 0
    );

    foreach ($compteurs_synth as $label => $value) {
        $newsheet3->setCellValue('A' . $rowNumber, $label);
        $newsheet3->setCellValue('B' . $rowNumber, $value);
        cellBorder($newsheet3, 'A' . $rowNumber . ':B' . $rowNumber);
        $rowNumber++;
    }

    $rowNumber += 2;

    // Répartition par marque
    if (!empty($marque_data)) {
        $newsheet3->mergeCells('D' . $rowNumber . ':E' . $rowNumber);
        cellStyle($newsheet3, 'D' . $rowNumber, 14);
        $newsheet3->setCellValue('D' . $rowNumber, 'REPARTITION PAR MARQUE');
        cellColor($newsheet3, 'D' . $rowNumber . ':E' . $rowNumber, '4472C4');
        $newsheet3->getStyle('D' . $rowNumber . ':E' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
        $rowNumber++;

        $newsheet3->setCellValue('D' . $rowNumber, 'Marque');
        $newsheet3->setCellValue('E' . $rowNumber, 'Nombre');
        cellStyle($newsheet3, 'D' . $rowNumber . ':E' . $rowNumber, 10);
        cellColor($newsheet3, 'D' . $rowNumber . ':E' . $rowNumber, '4472C4');
        $newsheet3->getStyle('D' . $rowNumber . ':E' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
        $rowNumber++;

        foreach ($marque_data as $data) {
            $marque = isset($data['marque']) ? $data['marque'] : 'Non défini';
            $newsheet3->setCellValue('D' . $rowNumber, $marque);
            $newsheet3->setCellValue('E' . $rowNumber, $data['total']);
            cellBorder($newsheet3, 'D' . $rowNumber . ':E' . $rowNumber);
            $rowNumber++;
        }
    }

    // État des compteurs
    if (!empty($etat_data)) {
        $rowNumber += 2;
        $newsheet3->mergeCells('G' . $rowNumber . ':H' . $rowNumber);
        cellStyle($newsheet3, 'G' . $rowNumber, 14);
        $newsheet3->setCellValue('G' . $rowNumber, 'ETAT DES COMPTEURS');
        cellColor($newsheet3, 'G' . $rowNumber . ':H' . $rowNumber, '4472C4');
        $newsheet3->getStyle('G' . $rowNumber . ':H' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
        $rowNumber++;

        $newsheet3->setCellValue('G' . $rowNumber, 'Etat');
        $newsheet3->setCellValue('H' . $rowNumber, 'Nombre');
        cellStyle($newsheet3, 'G' . $rowNumber . ':H' . $rowNumber, 10);
        cellColor($newsheet3, 'G' . $rowNumber . ':H' . $rowNumber, '4472C4');
        $newsheet3->getStyle('G' . $rowNumber . ':H' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
        $rowNumber++;

        foreach ($etat_data as $data) {
            $newsheet3->setCellValue('G' . $rowNumber, isset($data['etat']) ? $data['etat'] : 'Non défini');
            $newsheet3->setCellValue('H' . $rowNumber, $data['total']);
            cellBorder($newsheet3, 'G' . $rowNumber . ':H' . $rowNumber);
            $rowNumber++;
        }
    }

    $newsheet3->getColumnDimension('A')->setAutoSize(true);
    $newsheet3->getColumnDimension('B')->setAutoSize(true);
    $newsheet3->getColumnDimension('D')->setAutoSize(true);
    $newsheet3->getColumnDimension('E')->setAutoSize(true);
    $newsheet3->getColumnDimension('G')->setAutoSize(true);
    $newsheet3->getColumnDimension('H')->setAutoSize(true);

    // ==================== FEUILLE 4 : CLIENTS ====================
    $newsheet4 = $objPHPExcel->createSheet();
    $newsheet4->setTitle("CLIENTS");

    $rowNumber = 1;
    $newsheet4->mergeCells('A' . $rowNumber . ':B' . $rowNumber);
    cellStyle($newsheet4, 'A' . $rowNumber, 14);
    $newsheet4->setCellValue('A' . $rowNumber, 'REPARTITION DES CLIENTS');
    cellColor($newsheet4, 'A' . $rowNumber . ':B' . $rowNumber, '4472C4');
    $newsheet4->getStyle('A' . $rowNumber . ':B' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
    $rowNumber += 2;

    $type_client_labels = array(
        '0' => 'Résidentiel',
        '1' => 'Commercial',
        '2' => 'Industriel',
        '3' => 'Administration',
        '4' => 'Autre'
    );

    $newsheet4->setCellValue('A' . $rowNumber, 'Type de client');
    $newsheet4->setCellValue('B' . $rowNumber, 'Nombre');
    cellStyle($newsheet4, 'A' . $rowNumber . ':B' . $rowNumber, 10);
    cellColor($newsheet4, 'A' . $rowNumber . ':B' . $rowNumber, '4472C4');
    $newsheet4->getStyle('A' . $rowNumber . ':B' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
    $rowNumber++;

    if (!empty($type_client_data)) {
        foreach ($type_client_data as $data) {
            $label = isset($type_client_labels[$data['type_client']]) ? $type_client_labels[$data['type_client']] : 'Non défini';
            $newsheet4->setCellValue('A' . $rowNumber, $label);
            $newsheet4->setCellValue('B' . $rowNumber, $data['total']);
            cellBorder($newsheet4, 'A' . $rowNumber . ':B' . $rowNumber);
            $rowNumber++;
        }
    } else {
        $newsheet4->setCellValue('A' . $rowNumber, 'Aucune donnée disponible');
        $newsheet4->mergeCells('A' . $rowNumber . ':B' . $rowNumber);
        $rowNumber++;
    }

    $newsheet4->getColumnDimension('A')->setAutoSize(true);
    $newsheet4->getColumnDimension('B')->setAutoSize(true);

    // ==================== FEUILLE 5 : EQUIPES ====================
    if (!empty($equipe_data)) {
        $newsheet5 = $objPHPExcel->createSheet();
        $newsheet5->setTitle("EQUIPES");

        $rowNumber = 1;
        $newsheet5->mergeCells('A' . $rowNumber . ':C' . $rowNumber);
        cellStyle($newsheet5, 'A' . $rowNumber, 14);
        $newsheet5->setCellValue('A' . $rowNumber, 'PERFORMANCE DES EQUIPES');
        cellColor($newsheet5, 'A' . $rowNumber . ':C' . $rowNumber, '4472C4');
        $newsheet5->getStyle('A' . $rowNumber . ':C' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
        $rowNumber += 2;

        $newsheet5->setCellValue('A' . $rowNumber, 'Equipe');
        $newsheet5->setCellValue('B' . $rowNumber, 'Identifications');
        $newsheet5->setCellValue('C' . $rowNumber, 'Installations');
        cellStyle($newsheet5, 'A' . $rowNumber . ':C' . $rowNumber, 10);
        cellColor($newsheet5, 'A' . $rowNumber . ':C' . $rowNumber, '4472C4');
        $newsheet5->getStyle('A' . $rowNumber . ':C' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
        $rowNumber++;

        foreach ($equipe_data as $data) {
            $newsheet5->setCellValue('A' . $rowNumber, isset($data['equipe']) ? $data['equipe'] : 'Sans équipe');
            $newsheet5->setCellValue('B' . $rowNumber, $data['total_identifications']);
            $newsheet5->setCellValue('C' . $rowNumber, isset($data['installes']) ? $data['installes'] : 0);
            cellBorder($newsheet5, 'A' . $rowNumber . ':C' . $rowNumber);
            $rowNumber++;
        }

        $newsheet5->getColumnDimension('A')->setAutoSize(true);
        $newsheet5->getColumnDimension('B')->setAutoSize(true);
        $newsheet5->getColumnDimension('C')->setAutoSize(true);
    }

    // ==================== FEUILLE 6 : IDENTIFICATEURS ====================
    if (!empty($identificateur_data)) {
        $newsheet6 = $objPHPExcel->createSheet();
        $newsheet6->setTitle("IDENTIFICATEURS");

        $rowNumber = 1;
        $newsheet6->mergeCells('A' . $rowNumber . ':C' . $rowNumber);
        cellStyle($newsheet6, 'A' . $rowNumber, 14);
        $newsheet6->setCellValue('A' . $rowNumber, 'TOP 10 IDENTIFICATEURS');
        cellColor($newsheet6, 'A' . $rowNumber . ':C' . $rowNumber, '4472C4');
        $newsheet6->getStyle('A' . $rowNumber . ':C' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
        $rowNumber += 2;

        $newsheet6->setCellValue('A' . $rowNumber, 'Rang');
        $newsheet6->setCellValue('B' . $rowNumber, 'Identificateur');
        $newsheet6->setCellValue('C' . $rowNumber, 'Identifications');
        cellStyle($newsheet6, 'A' . $rowNumber . ':C' . $rowNumber, 10);
        cellColor($newsheet6, 'A' . $rowNumber . ':C' . $rowNumber, '4472C4');
        $newsheet6->getStyle('A' . $rowNumber . ':C' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
        $rowNumber++;

        $rang = 1;
        foreach ($identificateur_data as $data) {
            $newsheet6->setCellValue('A' . $rowNumber, $rang);
            $newsheet6->setCellValue('B' . $rowNumber, isset($data['identificateur']) ? $data['identificateur'] : 'Inconnu');
            $newsheet6->setCellValue('C' . $rowNumber, $data['total_identifications']);
            cellBorder($newsheet6, 'A' . $rowNumber . ':C' . $rowNumber);
            
            if ($rang == 1) {
                cellColor($newsheet6, 'A' . $rowNumber . ':C' . $rowNumber, 'FFD700');
            } elseif ($rang == 2) {
                cellColor($newsheet6, 'A' . $rowNumber . ':C' . $rowNumber, 'C0C0C0');
            } elseif ($rang == 3) {
                cellColor($newsheet6, 'A' . $rowNumber . ':C' . $rowNumber, 'CD7F32');
            }
            
            $rang++;
            $rowNumber++;
        }

        $newsheet6->getColumnDimension('A')->setAutoSize(true);
        $newsheet6->getColumnDimension('B')->setAutoSize(true);
        $newsheet6->getColumnDimension('C')->setAutoSize(true);
    }

    // ==================== FEUILLE 7 : REPARTITION PAR SITE ====================
    if (!empty($site_data)) {
        $newsheet7 = $objPHPExcel->createSheet();
        $newsheet7->setTitle("REPARTITION PAR SITE");

        $rowNumber = 1;
        $newsheet7->mergeCells('A' . $rowNumber . ':C' . $rowNumber);
        cellStyle($newsheet7, 'A' . $rowNumber, 14);
        $newsheet7->setCellValue('A' . $rowNumber, 'REPARTITION PAR SITE');
        cellColor($newsheet7, 'A' . $rowNumber . ':C' . $rowNumber, '4472C4');
        $newsheet7->getStyle('A' . $rowNumber . ':C' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
        $rowNumber += 2;

        $newsheet7->setCellValue('A' . $rowNumber, 'Site');
        $newsheet7->setCellValue('B' . $rowNumber, 'Clients');
        $newsheet7->setCellValue('C' . $rowNumber, 'Compteurs');
        cellStyle($newsheet7, 'A' . $rowNumber . ':C' . $rowNumber, 10);
        cellColor($newsheet7, 'A' . $rowNumber . ':C' . $rowNumber, '4472C4');
        $newsheet7->getStyle('A' . $rowNumber . ':C' . $rowNumber)->getFont()->getColor()->setARGB('FFFFFFFF');
        $rowNumber++;

        foreach ($site_data as $data) {
            $newsheet7->setCellValue('A' . $rowNumber, isset($data['site']) ? $data['site'] : 'Sans site');
            $newsheet7->setCellValue('B' . $rowNumber, $data['clients']);
            $newsheet7->setCellValue('C' . $rowNumber, $data['compteurs']);
            cellBorder($newsheet7, 'A' . $rowNumber . ':C' . $rowNumber);
            $rowNumber++;
        }

        $newsheet7->getColumnDimension('A')->setAutoSize(true);
        $newsheet7->getColumnDimension('B')->setAutoSize(true);
        $newsheet7->getColumnDimension('C')->setAutoSize(true);
    }

    // Supprimer les feuilles vides
    $sheets = $objPHPExcel->getSheetNames();
    foreach ($sheets as $sheet_name) {
        $sheet = $objPHPExcel->getSheetByName($sheet_name);
        if ($sheet) {
            $highestRow = $sheet->getHighestRow();
            if ($highestRow <= 3) {
                $objPHPExcel->removeSheetByIndex($objPHPExcel->getIndex($sheet));
            }
        }
    }

    // Nettoyer la mémoire
    ob_clean();

    // Envoyer le fichier
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="tableau_bord_compteurs.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');

    $objWriter = new Xlsx($objPHPExcel);
    $objWriter->save('php://output');
    exit();

} catch (Exception $exception) {
    error_log("Error in dashboard report: " . $exception->getMessage());
    echo "An error occurred: " . $exception->getMessage();
}
?>