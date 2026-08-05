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

    // Titre du site
    $newsheet->setCellValue("A" . $rowNumber, "LISTE DES COMPTEURS CONTROLES - " . $site_classe->intitule_site);
    cellStyle($newsheet, 'A' . $rowNumber, 14);
    $rowNumber++;
    
    $cell = 'Période : du ' . $du_ . ' au ' . $au_;
    $newsheet->setCellValue($col . $rowNumber, $cell);
    cellStyle($newsheet, $col . $rowNumber, 13);
    $rowNumber++;
    $rowNumber++;

    // EN-TETES PRINCIPALES
    cellStyle($newsheet, 'A' . $rowNumber, 10);
    $newsheet->setCellValue('A' . $rowNumber, "N°");
    cellAlign($newsheet, 'A' . $rowNumber, 'C');
    cellColor($newsheet, 'A' . $rowNumber, 'b3d0ee');

    $colons = array('B', 'C', 'D', 'E', 'F', 'G');
    foreach ($colons as $mycol) {
        cellBorder($newsheet, $mycol . $rowNumber);
        cellStyle($newsheet, $mycol . $rowNumber, 10);
        cellAlign($newsheet, $mycol . $rowNumber, 'C');
        cellColor($newsheet, $mycol . $rowNumber, 'b3d0ee');
        $newsheet->getColumnDimension($mycol)->setAutoSize(true);
    }

    $colons = array('H', 'I', 'J', 'K', 'L', 'M');
    foreach ($colons as $mycol) {
        cellBorder($newsheet, $mycol . $rowNumber);
        cellStyle($newsheet, $mycol . $rowNumber, 10);
        cellAlign($newsheet, $mycol . $rowNumber, 'C');
        cellColor($newsheet, $mycol . $rowNumber, 'b3d0ee');
        $newsheet->getColumnDimension($mycol)->setAutoSize(true);
    }

    $colons = array('N', 'O', 'P', 'Q');
    foreach ($colons as $mycol) {
        cellBorder($newsheet, $mycol . $rowNumber);
        cellStyle($newsheet, $mycol . $rowNumber, 10);
        cellAlign($newsheet, $mycol . $rowNumber, 'C');
        cellColor($newsheet, $mycol . $rowNumber, 'b3d0ee');
        $newsheet->getColumnDimension($mycol)->setAutoSize(true);
    }

    $headers_ = array(
        "Date du controle", "Controleur", "Quartier", "CVS", 
        "Adresse (Avenue et N°)", "Noms et Postnoms", "Date Installation", 
        "N° de Compteur à vérifier", "N° du Compteur trouvé", 
        "Date du dernier ticket", "Montant du dernier ticket", 
        "Crédit restant", "Conso 30 jours", "Etat de fraude", 
        "Type(s) de fraude", "Code(s) du diagnostic", "Observations complémentaires"
    );

    $col = 'A';
    foreach ($headers_ as $cell) {
        $newsheet->setCellValue($col . $rowNumber, $cell);
        $col++;
    }
    $rowNumber++;
    $ctr_context = 1;

    // ITERATION DATA POUR CHAQUE CVS
    foreach ($cvs_list as $cvs_item) {
        $data_ = $cls_report->getCVS_CompteursControl($cvs_item["code"], $du, $au);
        $nb_data_ = count($data_);
        
        if ($nb_data_ > 0) {
            foreach ($data_ as $row_) {
                $col = 'A';
                $date_controle_fr = isset($row_["date_controle_fr"]) ? $row_["date_controle_fr"] : '';
                $controleur_name = $utilisateur->GetUserDetailName($row_['controleur'] ?? '');

                $cvs->code = isset($row_["cvs_id"]) ? $row_["cvs_id"] : '';
                $cvs->GetDetailIN();
                
                $E_item = $adresseItem->GetAdressInfo($row_["adresse_id"] ?? '');
                $numero = '';
                $avenue = '';
                $quartier_ = '';
                
                if ($E_item !== false && is_array($E_item)) {
                    $numero = isset($E_item['numero']) ? $E_item['numero'] : '';
                    $avenue = $adresseItem->GetLabel($E_item['avenue'] ?? '');
                    $quartier_ = $adresseItem->GetLabel($E_item['quartier_id'] ?? '');
                }
                
                $cvs_libelle = $cvs->libelle ?? '';
                $adresse = $avenue . ' ' . $numero;
                $nom_postnom = isset($row_["nom_client_blue"]) ? $row_["nom_client_blue"] : '';

                $row_install = $Installation->GetDetail_Light($row_["ref_last_install_found"] ?? '');
                if ($row_install === false || $row_install === null) {
                    $row_install = array();
                }

                $date_installation = isset($row_install["date_fin_installation_fr"]) ? $row_install["date_fin_installation_fr"] : '';
                $numero_compteur_a_verifier = isset($row_install["numero_compteur"]) ? $row_install["numero_compteur"] : '';
                $numero_compteur_trouver = isset($row_["numero_serie_cpteur"]) ? $row_["numero_serie_cpteur"] : '';
                
                $date_de_dernier_ticket_rentre = '';
                if (!empty($row_["date_de_dernier_ticket_rentre"])) {
                    $date_de_dernier_ticket_rentre = Utils::DbToClientDateFormat($row_["date_de_dernier_ticket_rentre"]);
                }
                
                $valeur_du_dernier_ticket = isset($row_["date_de_dernier_ticket_rentre"]) ? $row_["date_de_dernier_ticket_rentre"] : '';
                $credit_restant = isset($row_["credit_restant"]) ? $row_["credit_restant"] : '';
                $consommation_de_30jours_actuels = isset($row_["consommation_de_30jours_actuels"]) ? $row_["consommation_de_30jours_actuels"] : '';
                $etat_fraude = isset($row_["cas_de_fraude"]) ? $row_["cas_de_fraude"] : '';

                $item_ctl->ref_fiche_controle = isset($row_["ref_fiche_controle"]) ? $row_["ref_fiche_controle"] : '';
                $data = $item_ctl->GetDetail($utilisateur->id_service_group ?? 0);
                
                $type_fraudes = "";
                if (!empty($data['fraudes'])) {
                    $lst_fraudes_selected = "";
                    foreach ($data['fraudes'] as $value_) {
                        $typeFraude->code = $value_['ref_code_fraude'] ?? '';
                        $typeFraude->GetDetailIN();
                        $lst_fraudes_selected .= ($typeFraude->code_label ?? '') . "-";
                    }
                    $type_fraudes = rtrim($lst_fraudes_selected, "-");
                }

                $type_codes_diagnostics = "";
                if (!empty($data['codes_observations'])) {
                    $lst_type_codes_diagnostics_selected = "";
                    foreach ($data['codes_observations'] as $value_) {
                        $typeObservation->code = $value_['ref_code_obs'] ?? '';
                        $typeObservation->GetDetailIN();
                        $lst_type_codes_diagnostics_selected .= ($typeObservation->code_label ?? '') . "-";
                    }
                    $type_codes_diagnostics = rtrim($lst_type_codes_diagnostics_selected, "-");
                }

                $diagnostics_general = isset($row_["diagnostics_general"]) ? $row_["diagnostics_general"] : '';

                // ÉCRITURE DES DONNÉES SANS NUMÉROTATION
                $newsheet->setCellValue($col . $rowNumber, $date_controle_fr);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $controleur_name);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $quartier_);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $cvs_libelle);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $adresse);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $nom_postnom);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $date_installation);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $numero_compteur_a_verifier);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $numero_compteur_trouver);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $date_de_dernier_ticket_rentre);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $valeur_du_dernier_ticket);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $credit_restant);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $consommation_de_30jours_actuels);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $etat_fraude);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $type_fraudes);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $type_codes_diagnostics);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $newsheet->setCellValue($col . $rowNumber, $diagnostics_general);
                cellBorder($newsheet, $col . $rowNumber);
                $col++;

                $rowNumber++;
                $ctr_context++;
            }
        }
    }
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="rapport_compteur_control.xlsx"');
header('Cache-Control: max-age=0');

$objWriter = new Xlsx($objPHPExcel);
$objWriter->save('php://output');
exit();
?>