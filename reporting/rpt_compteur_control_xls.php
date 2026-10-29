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
require_once '../classes/Installation.php';
require_once '../classes/PARAM_TypeFraude.php';
require_once '../classes/PARAM_TypeObservation.php';
require_once '../classes/CLS_Controle.php';
include_once '../core.php';
header('Content-type: text/html;charset=utf-8');
// Un avertissement affiché au milieu du .xlsx rend le fichier illisible
ini_set('display_errors', '0');
set_time_limit(600);

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
$Installation = new Installation($db);
$adresseItem = new AdresseEntity($db);
$cvs = new CVS($db);
$marquecompteur = new MarqueCompteur($db);
$typeFraude = new PARAM_TypeFraude($db);
$typeObservation = new PARAM_TypeObservation($db);

$item_ctl = new CLS_Controle($db);
$site_classe = new Site($db);
$liste_site = array();
$header_bg_color = "#ECF2FE";
$site = isset($_POST['site']) ? $_POST['site'] : NULL;
$du = isset($_POST['Du']) ? Utils::ClientToDbDateFormat($_POST['Du']) : "";
$au = isset($_POST['Au']) ? Utils::ClientToDbDateFormat($_POST['Au']) : "";
$du_ = isset($_POST['Du']) ? ($_POST['Du']) : "";
$au_ = isset($_POST['Au']) ? ($_POST['Au']) : "";
$utilisateur->is_logged_in();
$utilisateur->readOne();

// Filtre d'approbation optionnel : '1' = approuvés, '0' = pas approuvés, '' = tous (les brouillons sont toujours exclus)
$approbation_filter = '';
if (isset($_POST['approbation']) && $_POST['approbation'] !== '') {
    $approbation_filter = " and t_log_controle.approbation_control='" . addslashes($_POST['approbation']) . "'";
}

function cellAlign($newsheet, $cells, $align)
{
    if ($align == "R") {
        $newsheet->getStyle($cells)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    } else if ($align == "J") {
        $newsheet->getStyle($cells)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_JUSTIFY);
    } else if ($align == "C") {
        $newsheet->getStyle($cells)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }
}

function cellBorder($newsheet, $cells)
{
    $newsheet->getStyle($cells)->applyFromArray(
        [
            'borders' => [
                'outline' => [
                    'style' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]
    );
}

function cellStyle($newsheet, $cells, $size)
{
    $newsheet->getStyle($cells)->getFont()->setSize($size);
    $newsheet->getStyle($cells)->getFont()->setBold(true);
}

function cellColor($newsheet, $cells, $color)
{
    $newsheet->getStyle($cells)->getFill()->applyFromArray([
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => $color,
        ],
    ]);
}

function sanitizeSheetTitle($title, $maxLength = 31)
{
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

// Fonction pour appliquer trim et alignement à droite sur une plage de cellules
function applyTrimAndAlignRight($newsheet, $startRow, $endRow, $startCol, $endCol)
{
    // Appliquer l'alignement à droite sur toute la plage
    $range = $startCol . $startRow . ':' . $endCol . $endRow;
    $newsheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    
    // Appliquer trim sur chaque cellule
    for ($row = $startRow; $row <= $endRow; $row++) {
        for ($col = $startCol; $col <= $endCol; $col++) {
            $cell = $newsheet->getCell($col . $row);
            $value = $cell->getValue();
            if (is_string($value)) {
                $trimmedValue = trim($value);
                if ($trimmedValue !== $value) {
                    $cell->setValue($trimmedValue);
                }
            }
        }
    }
}

// Caches des libellés (évite une requête par ligne)
$cache_labels = array();
function cachedLabel(&$cache, $type, $code, $loader)
{
    $code = (string)$code;
    if (!isset($cache[$type][$code])) {
        $cache[$type][$code] = $code === '' ? '' : trim((string)$loader($code));
    }
    return $cache[$type][$code];
}

if (in_array($MULTI_ACCESS_SITE_CODE, $site)) {
    $liste_site = $cls_report->GetAll_AccessibleUSerSite($utilisateur->code_utilisateur);
} else {
    $liste_site = $site;
}

$ctr_cvs = 0;
$objPHPExcel = new Spreadsheet();
$objPHPExcel->setActiveSheetIndex(0);
$newsheet = $objPHPExcel->getActiveSheet();
$newsheet->setTitle("SYNTHESE");

// Ajout du logo
$drawing = new Drawing();
$drawing->setName('Logo');
$drawing->setDescription('Company Logo');
$drawing->setPath(__DIR__ . '/images/logo2.png');
$drawing->setHeight(100);
$drawing->setWidth(180);
$drawing->setCoordinates('A1');
$drawing->setWorksheet($newsheet);

// Ajout des informations société
$range = 'G1:L6';
$text = "Société Anonyme avec Conseil d'Administration
Adresse : Galerie Gombe River
Avenue des forces armées n°1022
Commune de la Gombe
Kinshasa R. D. C";

$newsheet->mergeCells($range);
$newsheet->setCellValue('G1', $text);

$style = $newsheet->getStyle($range);
$style->getFont()->setBold(true)->setSize(12);
$style->getAlignment()
    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT)
    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
$style->getAlignment()->setWrapText(true);

foreach ($liste_site as $site_item) {
    $site_classe->code_site = $site_item;
    $site_classe->GetDetailIN();

    $cvs_list = $cls_report->GetAll_Site_CVSList($site_item);
    $count_cvs = count($cvs_list);
    $ctr_cvs = 0;
    $start_l = false;
    $rowNumber = 7;

    $newsheet = $objPHPExcel->getActiveSheet();
    //DEBUT SYNTHESE
    $newsheet->setTitle("SYNTHESE");
    $newsheet->mergeCells('B' . $rowNumber . ':H' . $rowNumber);
    cellStyle($newsheet, 'B' . $rowNumber, 14);
    $newsheet->setCellValue('B' . $rowNumber, 'TABLEAU RESUME  du ' . $du_ . ' au ' . $au_);
    cellBorder($newsheet, 'B' . $rowNumber . ':H' . $rowNumber);
    cellAlign($newsheet, 'B' . $rowNumber, 'C');
    cellColor($newsheet, 'B' . $rowNumber, 'b6b8b9');

    //////////////////CONTROLES /////////////////////////
    $rowNumber++;
    $rowNumber++;
    cellStyle($newsheet, 'A' . $rowNumber, 13);
    $newsheet->setCellValue('A' . $rowNumber, 'Compteurs contrôlés');
    $rowNumber++;
    $rowNumber++;
    $Total_sceller = 0;
    $Total_install = 0;
    $col = 'A';
    $rowNumber_titre = $rowNumber;
    $rowNumber_install = $rowNumber + 1;
    $rowNumber_replace = $rowNumber + 2;

    $newsheet->setCellValue($col . $rowNumber_install, 'Contrôlés');
    cellBorder($newsheet, $col . $rowNumber_install);

    $newsheet->setCellValue($col . $rowNumber_replace, 'Fraudes');
    cellBorder($newsheet, $col . $rowNumber_replace);

    $col = 'B';
    foreach ($cvs_list as $cvs_item) {
        $nbre_install = $cls_report->getCVS_CompteursControlPeriodeCount($cvs_item["code"], $du, $au);
        $nbre_sceller = $cls_report->getCVS_CompteursFraudePeriodeCount($cvs_item["code"], $du, $au);

        $Total_sceller += $nbre_sceller;
        $Total_install += $nbre_install;

        $newsheet->setCellValue($col . $rowNumber_titre, 'CVS/' . $cvs_item["libelle"]);
        $newsheet->getColumnDimension($col)->setAutoSize(true);
        cellBorder($newsheet, $col . $rowNumber_titre);
        cellStyle($newsheet, $col . $rowNumber_titre, 10);

        $newsheet->setCellValue($col . $rowNumber_install, $nbre_install . ' ');
        cellBorder($newsheet, $col . $rowNumber_install);

        $newsheet->setCellValue($col . $rowNumber_replace, $nbre_sceller . ' ');
        cellBorder($newsheet, $col . $rowNumber_replace);

        $col++;
    }

    $newsheet->setCellValue($col . $rowNumber_titre, 'TOTAL');
    cellBorder($newsheet, $col . $rowNumber_titre);
    cellStyle($newsheet, $col . $rowNumber_titre, 10);

    $newsheet->setCellValue($col . $rowNumber_install, $Total_install . ' ');
    cellBorder($newsheet, $col . $rowNumber_install);

    $newsheet->setCellValue($col . $rowNumber_replace, $Total_sceller . ' ');
    cellBorder($newsheet, $col . $rowNumber_replace);

    $rowNumber = $rowNumber + 3;
    //////////////////END CONTROLE  /////////////////////////
}

// FIN SYNTHESE - FEUILLE DETAILLEE
foreach ($liste_site as $site_item) {
    $site_classe->code_site = $site_item;
    $site_classe->GetDetailIN();

    $cvs_list = $cls_report->GetAll_Site_CVSList($site_item);
    $count_cvs = count($cvs_list);
    $start_l = false;

    $rowNumber = 7;
    $col = 'A';
    $newsheet = $objPHPExcel->createSheet();
    $sheetTitle = sanitizeSheetTitle('Compteurs_controles_' . $site_classe->intitule_site);
    $newsheet->setTitle($sheetTitle);

    // Ajout du logo sur chaque feuille détaillée
    $drawing = new Drawing();
    $drawing->setName('Logo');
    $drawing->setDescription('Company Logo');
    $drawing->setPath(__DIR__ . '/images/logo2.png');
    $drawing->setHeight(100);
    $drawing->setWidth(180);
    $drawing->setCoordinates('A1');
    $drawing->setWorksheet($newsheet);

    // Titre du site avec fond coloré (comme dans le premier fichier)
    $range = 'A7:J7';
    $newsheet->setCellValue("A7", "LISTE DES COMPTEURS CONTROLES - " . $site_classe->intitule_site);
    $newsheet->getStyle($range)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFE0D5BD');
    $newsheet->getStyle($range)->getFont()->setSize(14)->setBold(true);
    $rowNumber = 8;
    
    $cell = 'Période : du ' . $du_ . ' au ' . $au_;
    $range = 'A8:J8';
    $newsheet->setCellValue($col . $rowNumber, $cell);
    $newsheet->getStyle($range)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFE0D5BD');
    $newsheet->getStyle($range)->getFont()->setSize(13)->setBold(true);
    $rowNumber++;
    $rowNumber++;

    // EN-TETES PRINCIPALES avec style comme dans le premier fichier
    // Fusion des cellules d'en-tête comme dans le premier fichier
    $newsheet->mergeCells('A' . $rowNumber . ':A' . ($rowNumber + 1));
    cellStyle($newsheet, 'A' . $rowNumber, 10);
    $newsheet->setCellValue('A' . $rowNumber, "N°");
    cellAlign($newsheet, 'A' . $rowNumber, 'C');
    cellColor($newsheet, 'A' . $rowNumber, '17a2b8'); // Couleur bleue comme dans le premier fichier

    // Fusion pour les colonnes d'infos client
    $newsheet->mergeCells('B' . $rowNumber . ':F' . $rowNumber);
    cellStyle($newsheet, 'B' . $rowNumber, 10);
    $newsheet->setCellValue('B' . $rowNumber, "INFOS DU CLIENT");
    cellBorder($newsheet, 'B' . $rowNumber . ':F' . $rowNumber);
    cellAlign($newsheet, 'B' . $rowNumber, 'C');
    cellColor($newsheet, 'B' . $rowNumber, '17a2b8');

    // Fusion pour les colonnes d'installation
    $newsheet->mergeCells('G' . $rowNumber . ':J' . $rowNumber);
    cellStyle($newsheet, 'G' . $rowNumber, 10);
    $newsheet->setCellValue('G' . $rowNumber, "INSTALLATION");
    cellBorder($newsheet, 'G' . $rowNumber . ':J' . $rowNumber);
    cellAlign($newsheet, 'G' . $rowNumber, 'C');
    cellColor($newsheet, 'G' . $rowNumber, 'ffc107');

    // Fusion pour les colonnes de contrôle
    $newsheet->mergeCells('K' . $rowNumber . ':Q' . $rowNumber);
    cellStyle($newsheet, 'K' . $rowNumber, 10);
    $newsheet->setCellValue('K' . $rowNumber, "CONTROLE");
    cellBorder($newsheet, 'K' . $rowNumber . ':Q' . $rowNumber);
    cellAlign($newsheet, 'K' . $rowNumber, 'C');
    cellColor($newsheet, 'K' . $rowNumber, '007bff');

    // Fusion pour les colonnes de fraude et observations
    $newsheet->mergeCells('R' . $rowNumber . ':S' . $rowNumber);
    cellStyle($newsheet, 'R' . $rowNumber, 10);
    $newsheet->setCellValue('R' . $rowNumber, "FRAUDE & OBSERVATIONS");
    cellBorder($newsheet, 'R' . $rowNumber . ':S' . $rowNumber);
    cellAlign($newsheet, 'R' . $rowNumber, 'C');
    cellColor($newsheet, 'R' . $rowNumber, '28a745');

    $rowNumber++;

    // Deuxième ligne d'en-têtes (sous-titres)
    $headers_ = array(
        "", // N°
        "Quartier", "CVS", "Adresse", "Noms et Postnoms",
        "Date debut inst.", "Date fin inst.", "N° Compteur vérifier", "N° Compteur trouvé",
        "Date controle", "Controleur", "Statut",
        "Date dernier ticket", "Montant ticket", "Crédit restant", "Conso 30 jours",
        "Etat fraude", "Type(s) fraude", "Code(s) diagnostic", "Observations"
    );

    // Ajout des colonnes pour les sous-titres
    $col = 'A';
    foreach ($headers_ as $cell) {
        $newsheet->setCellValue($col . $rowNumber, $cell);
        cellBorder($newsheet, $col . $rowNumber);
        cellStyle($newsheet, $col . $rowNumber, 9);
        cellAlign($newsheet, $col . $rowNumber, 'C');
        $newsheet->getColumnDimension($col)->setAutoSize(true);
        $col++;
    }
    $rowNumber++;
    $ctr_context = 1;
    
    // Sauvegarder la ligne de début des données pour appliquer trim et alignement à droite
    $startDataRow = $rowNumber;

    // ITERATION DATA POUR CHAQUE CVS
    $data_par_cvs = array();
    $refs_controle = array();
    foreach ($cvs_list as $cvs_item) {
        $data_par_cvs[$cvs_item["code"]] = $cls_report->getCVS_CompteursControl($cvs_item["code"], $du, $au, $approbation_filter);
        foreach ($data_par_cvs[$cvs_item["code"]] as $r_) {
            $refs_controle[] = $r_["ref_fiche_controle"];
        }
    }
    $fraudes_obs = $cls_report->GetFraudesObservationsControles($refs_controle);

    foreach ($cvs_list as $cvs_item) {
        $data_ = $data_par_cvs[$cvs_item["code"]];
        $nb_data_ = count($data_);
        
        if ($nb_data_ > 0) {
            foreach ($data_ as $row_) {
                $col = 'A';
                $date_controle_fr = isset($row_["date_controle_fr"]) ? trim($row_["date_controle_fr"]) : '';
                $controleur_name = cachedLabel($cache_labels, 'user', $row_['controleur'] ?? '', function ($c) use ($utilisateur) {
                    return $utilisateur->GetUserDetailName($c);
                });

                $cvs_libelle = cachedLabel($cache_labels, 'cvs', $row_["cvs_id"] ?? '', function ($c) use ($cvs) {
                    $cvs->code = $c;
                    $cvs->GetDetailIN();
                    return $cvs->libelle ?? '';
                });
                
                $E_item = $adresseItem->GetAdressInfo($row_["adresse_id"] ?? '');
                $numero = '';
                $avenue = '';
                $quartier_ = '';
                
                if ($E_item !== false && is_array($E_item)) {
                    $numero = isset($E_item['numero']) ? trim($E_item['numero']) : '';
                    $avenue = cachedLabel($cache_labels, 'entite', $E_item['avenue'] ?? '', array($adresseItem, 'GetLabel'));
                    $quartier_ = cachedLabel($cache_labels, 'entite', $E_item['quartier_id'] ?? '', array($adresseItem, 'GetLabel'));
                }
                
                $adresse = trim($avenue . ' ' . $numero);
                $nom_postnom = isset($row_["nom_client_blue"]) ? trim($row_["nom_client_blue"]) : '';

                // Récupération des données d'installation
                $row_install = $Installation->GetDetail_Light($row_["ref_last_install_found"] ?? '');
                if ($row_install === false || $row_install === null) {
                    $row_install = array();
                }

                // Date début d'installation
                $date_debut_installation = '';
                if (!empty($row_install) && isset($row_install["date_debut_installation_fr"])) {
                    $date_debut_installation = trim($row_install["date_debut_installation_fr"]);
                } elseif (!empty($row_install) && isset($row_install["date_debut_installation"])) {
                    try {
                        $date_debut_installation = trim(Utils::DbToClientDateFormat($row_install["date_debut_installation"]));
                    } catch (Exception $e) {
                        $date_debut_installation = trim($row_install["date_debut_installation"]);
                    }
                }

                // Date fin d'installation
                $date_fin_installation = '';
                if (!empty($row_install) && isset($row_install["date_fin_installation_fr"])) {
                    $date_fin_installation = trim($row_install["date_fin_installation_fr"]);
                } elseif (!empty($row_install) && isset($row_install["date_fin_installation"])) {
                    try {
                        $date_fin_installation = trim(Utils::DbToClientDateFormat($row_install["date_fin_installation"]));
                    } catch (Exception $e) {
                        $date_fin_installation = trim($row_install["date_fin_installation"]);
                    }
                }
                
                $numero_compteur_a_verifier = '';
                if (!empty($row_install) && isset($row_install["numero_compteur"])) {
                    $numero_compteur_a_verifier = trim($row_install["numero_compteur"]);
                }
                
                $numero_compteur_trouver = isset($row_["numero_serie_cpteur"]) ? trim($row_["numero_serie_cpteur"]) : '';
                
                $date_de_dernier_ticket_rentre = '';
                if (!empty($row_["date_de_dernier_ticket_rentre"])) {
                    $date_de_dernier_ticket_rentre = trim(Utils::DbToClientDateFormat($row_["date_de_dernier_ticket_rentre"]));
                }
                
                $valeur_du_dernier_ticket = isset($row_["valeur_du_dernier_ticket"]) ? trim($row_["valeur_du_dernier_ticket"]) : '';
                $credit_restant = isset($row_["credit_restant"]) ? trim($row_["credit_restant"]) : '';
                $consommation_de_30jours_actuels = isset($row_["consommation_de_30jours_actuels"]) ? trim($row_["consommation_de_30jours_actuels"]) : '';
                $etat_fraude = isset($row_["cas_de_fraude"]) ? trim($row_["cas_de_fraude"]) : '';

                // Fraudes et observations (chargées en une fois pour tout le site)
                $ref_ctl = isset($row_["ref_fiche_controle"]) ? $row_["ref_fiche_controle"] : '';
                $labels_fraudes = array();
                foreach (($fraudes_obs['fraudes'][$ref_ctl] ?? array()) as $code_) {
                    $labels_fraudes[] = cachedLabel($cache_labels, 'fraude', $code_, function ($c) use ($typeFraude) {
                        $typeFraude->code = $c;
                        $typeFraude->GetDetailIN();
                        return $typeFraude->code_label ?? '';
                    });
                }
                $type_fraudes = rtrim(trim(implode('-', $labels_fraudes) . (count($labels_fraudes) ? '-' : '')), "-");

                $labels_obs = array();
                foreach (($fraudes_obs['observations'][$ref_ctl] ?? array()) as $code_) {
                    $labels_obs[] = cachedLabel($cache_labels, 'observation', $code_, function ($c) use ($typeObservation) {
                        $typeObservation->code = $c;
                        $typeObservation->GetDetailIN();
                        return $typeObservation->code_label ?? '';
                    });
                }
                $type_codes_diagnostics = rtrim(trim(implode('-', $labels_obs) . (count($labels_obs) ? '-' : '')), "-");

                $diagnostics_general = isset($row_["diagnostics_general"]) ? trim($row_["diagnostics_general"]) : '';

                // ÉCRITURE DES DONNÉES AVEC STYLE DU PREMIER FICHIER
                // Colonne 1: N°
                $newsheet->setCellValue($col . $rowNumber, trim((string)$ctr_context));
                $col++;

                // Colonne 2: Quartier
                $newsheet->setCellValue($col . $rowNumber, $quartier_);
                $col++;

                // Colonne 3: CVS
                $newsheet->setCellValue($col . $rowNumber, $cvs_libelle);
                $col++;

                // Colonne 4: Adresse
                $newsheet->setCellValue($col . $rowNumber, $adresse);
                $col++;

                // Colonne 5: Noms et Postnoms
                $newsheet->setCellValue($col . $rowNumber, $nom_postnom);
                $col++;

                // Colonne 6: Date debut installation
                $newsheet->setCellValue($col . $rowNumber, $date_debut_installation);
                $col++;

                // Colonne 7: Date fin installation
                $newsheet->setCellValue($col . $rowNumber, $date_fin_installation);
                $col++;

                // Colonne 8: N° Compteur à vérifier
                $newsheet->setCellValue($col . $rowNumber, $numero_compteur_a_verifier);
                $col++;

                // Colonne 9: N° Compteur trouvé
                $newsheet->setCellValue($col . $rowNumber, $numero_compteur_trouver);
                $col++;

                // Colonne 10: Date controle
                $newsheet->setCellValue($col . $rowNumber, $date_controle_fr);
                $col++;

                // Colonne 11: Controleur
                $newsheet->setCellValue($col . $rowNumber, $controleur_name);
                $col++;

                // Colonne 12: Statut (approuvé / pas approuvé) — les brouillons sont exclus de cet export
                $statut_controle_export = (isset($row_["approbation_control"]) && $row_["approbation_control"] === '1') ? 'Approuvé' : 'Pas approuvé';
                $newsheet->setCellValue($col . $rowNumber, $statut_controle_export);
                $col++;

                // Colonne 12: Date dernier ticket
                $newsheet->setCellValue($col . $rowNumber, $date_de_dernier_ticket_rentre);
                $col++;

                // Colonne 13: Montant ticket
                $newsheet->setCellValue($col . $rowNumber, $valeur_du_dernier_ticket);
                $col++;

                // Colonne 14: Crédit restant
                $newsheet->setCellValue($col . $rowNumber, $credit_restant);
                $col++;

                // Colonne 15: Conso 30 jours
                $newsheet->setCellValue($col . $rowNumber, $consommation_de_30jours_actuels);
                $col++;

                // Colonne 16: Etat fraude
                $newsheet->setCellValue($col . $rowNumber, $etat_fraude);
                $col++;

                // Colonne 17: Type(s) fraude
                $newsheet->setCellValue($col . $rowNumber, $type_fraudes);
                $col++;

                // Colonne 18: Code(s) diagnostic
                $newsheet->setCellValue($col . $rowNumber, $type_codes_diagnostics);
                $col++;

                // Colonne 19: Observations
                $newsheet->setCellValue($col . $rowNumber, $diagnostics_general);
                $col++;

                // Alternance des couleurs de ligne (comme dans le premier fichier)
                if ($ctr_context % 2 == 0) {
                    $rowRange = 'A' . $rowNumber . ':T' . $rowNumber;
                    $newsheet->getStyle($rowRange)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->                        getStartColor()->setARGB('FFD6E2F0');
                }

                $rowNumber++;
                $ctr_context++;
            }
        }
    }
    
    // Bordures et alignement appliqués en une fois sur toutes les données (valeurs déjà trimées)
    if ($rowNumber > $startDataRow) {
        $lastDataRow = $rowNumber - 1;
        $newsheet->getStyle('A' . $startDataRow . ':T' . $lastDataRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        // Colonne Statut centrée
        $newsheet->getStyle('L' . $startDataRow . ':L' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="rapport_compteur_control.xlsx"');
header('Cache-Control: max-age=0');

$objWriter = new Xlsx($objPHPExcel);
$objWriter->save('php://output');
exit();
?>