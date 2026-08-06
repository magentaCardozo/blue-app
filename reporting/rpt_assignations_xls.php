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
$droits = new Droits();
$cls_report = new CLS_Reporting($db);
$cvs = new CVS($db);
$marquecompteur = new MarqueCompteur($db);
$adresseItem = new AdresseEntity($db);
$site_classe = new Site($db);
$organisme = new Organisme($db);

$liste_site = array();
$header_bg_color = "#ECF2FE";
$site = isset($_POST['site']) ? $_POST['site'] : NULL;
$du = isset($_POST['Du']) ? Utils::ClientToDbDateFormat($_POST['Du']) : "";
$au = isset($_POST['Au']) ? Utils::ClientToDbDateFormat($_POST['Au']) : "";
$du_ = isset($_POST['Du']) ? ($_POST['Du']) : "";
$au_ = isset($_POST['Au']) ? ($_POST['Au']) : "";
$selected_cvs = isset($_POST['cvs']) ? $_POST['cvs'] : array();

$utilisateur->is_logged_in();
$utilisateur->readOne();

try {
    if (in_array($MULTI_ACCESS_SITE_CODE, $site)) {
        $liste_site = $cls_report->GetAll_AccessibleUSerSite($utilisateur->code_utilisateur);
    } else {
        $liste_site = $site;
    }

    // ============================================
    // FONCTIONS UTILITAIRES
    // ============================================

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

    function cellBorderAll($newsheet, $cells)
    {
        $newsheet->getStyle($cells)->applyFromArray(
            [
                'borders' => [
                    'allBorders' => [
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

    function cellColorFont($newsheet, $cells, $color)
    {
        $newsheet->getStyle($cells)->getFont()->getColor()->setRGB($color);
    }

    function addLogoToSheet($worksheet, $path = null)
    {
        if ($path === null) {
            $path = __DIR__ . '\images\logo2.png';
        }
        
        if (file_exists($path)) {
            $drawing = new Drawing();
            $drawing->setName('Logo');
            $drawing->setDescription('Company Logo');
            $drawing->setPath($path);
            $drawing->setHeight(100);
            $drawing->setWidth(180);
            $drawing->setCoordinates('A1');
            $drawing->setWorksheet($worksheet);
            return $drawing;
        }
        return null;
    }

    function addHeaderToSheet($worksheet)
    {
        $range = 'G1:L6';
        $text = "Société Anonyme avec Conseil d'Administration\n";
        $text .= "Adresse : Galerie Gombe River\n";
        $text .= "Avenue des forces armées n°1022\n";
        $text .= "Commune de la Gombe\n";
        $text .= "Kinshasa R. D. C";

        $worksheet->mergeCells($range);
        $worksheet->setCellValue('G1', $text);

        $style = $worksheet->getStyle($range);
        $style->getFont()->setBold(true)->setSize(12);
        $style->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $style->getAlignment()->setWrapText(true);
    }

    // ============================================
    // FONCTIONS DE RÉCUPÉRATION DES DONNÉES
    // ============================================

    /**
     * Récupère les données d'assignation pour un CVS donné
     * 
     * @param PDO $db Connexion à la base de données
     * @param string $cvs_code Code du CVS
     * @param string $du Date de début (format YYYY-MM-DD HH:MM:SS)
     * @param string $au Date de fin (format YYYY-MM-DD HH:MM:SS)
     * @return array Tableau des données d'assignation
     */
function getAssignationData($db, $cvs_code, $du, $au)
{
    $query = "
        SELECT 
            DATE_FORMAT(STR_TO_DATE(a.datesys, '%Y-%m-%d %H:%i:%s'), '%d/%m/%Y') AS date_assignation,
            a.id_technicien,
            u.nom_complet AS technicien,
            cvs.libelle AS cvs,
            COUNT(DISTINCT a.id_assign) AS assignations_recues,
            COUNT(DISTINCT c.ref_fiche_controle) AS controles_realises,
            COUNT(DISTINCT a.id_assign) - COUNT(DISTINCT c.ref_fiche_controle) AS en_attente,
            ROUND((COUNT(DISTINCT c.ref_fiche_controle) / NULLIF(COUNT(DISTINCT a.id_assign), 0)) * 100, 1) AS taux_realisation,
            COUNT(DISTINCT 
                CASE 
                    WHEN o.ref_control_obs IS NOT NULL AND o.annule = 0 
                    THEN o.ref_control_obs 
                END
            ) AS non_conformites
        FROM 
            t_param_assignation a
            INNER JOIN t_utilisateurs u ON a.id_technicien = u.code_utilisateur
            INNER JOIN t_param_cvs cvs ON cvs.code = :cvs_code
            INNER JOIN t_main_data md ON a.id_fiche_identif = md.id_
            LEFT JOIN t_log_controle c ON md.id_ = c.ref_fiche_identification 
                AND c.annule = 0
                AND DATE(c.date_controle) = DATE(a.date_execution)
            LEFT JOIN t_log_controle_observations o ON c.ref_fiche_controle = o.ref_fiche_control 
                AND o.annule = 0
        WHERE 
            a.annule = 0 
            AND a.statut_ = 1
            AND a.type_assignation = 1
            AND md.cvs_id = :cvs_code
            AND STR_TO_DATE(a.datesys, '%Y-%m-%d %H:%i:%s') BETWEEN :du AND :au
        GROUP BY 
            DATE(STR_TO_DATE(a.datesys, '%Y-%m-%d %H:%i:%s')),
            a.id_technicien,
            u.nom_complet,
            cvs.libelle,
            a.datesys  -- Added a.datesys to GROUP BY
        ORDER BY 
            u.nom_complet,
            DATE(STR_TO_DATE(a.datesys, '%Y-%m-%d %H:%i:%s')) DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->bindValue(":cvs_code", $cvs_code);
    $stmt->bindValue(":du", $du);
    $stmt->bindValue(":au", $au);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    /**
     * Récupère la synthèse par technicien pour un CVS donné
     * 
     * @param PDO $db Connexion à la base de données
     * @param string $cvs_code Code du CVS
     * @param string $du Date de début (format YYYY-MM-DD HH:MM:SS)
     * @param string $au Date de fin (format YYYY-MM-DD HH:MM:SS)
     * @return array Tableau de la synthèse par technicien
     */
    function getSynthByTechnicien($db, $cvs_code, $du, $au)
    {
        $query = "
        SELECT
            t.technicien,
            SUM(t.assignations) AS assignations,
            SUM(t.controles) AS controles,
            SUM(t.assignations) - SUM(t.controles) AS en_attente,
            ROUND(
                (SUM(t.controles) / NULLIF(SUM(t.assignations), 0)) * 100,
                1
            ) AS taux
        FROM (
            SELECT
                u.nom_complet AS technicien,
                COUNT(DISTINCT a.id_assign) AS assignations,
                COUNT(DISTINCT c.ref_fiche_controle) AS controles
            FROM
                t_param_assignation a
                INNER JOIN t_utilisateurs u ON a.id_technicien = u.code_utilisateur
                INNER JOIN t_main_data md ON a.id_fiche_identif = md.id_
                LEFT JOIN t_log_controle c ON md.id_ = c.ref_fiche_identification
                    AND c.annule = 0
                    -- SUPPRESSION DE: AND c.statut_controle IN ('1', '2', 'R')
                    AND DATE(c.date_controle) = DATE(a.date_execution)
            WHERE
                a.annule = 0
                AND a.statut_ = 1
                AND a.type_assignation = 1
                AND md.cvs_id = :cvs_code
                AND STR_TO_DATE(a.datesys, '%Y-%m-%d %H:%i:%s') BETWEEN :du AND :au
            GROUP BY
                u.nom_complet
        ) t
        GROUP BY
            t.technicien
        ORDER BY
            taux DESC
        ";

        $stmt = $db->prepare($query);
        $stmt->bindValue(":cvs_code", $cvs_code);
        $stmt->bindValue(":du", $du);
        $stmt->bindValue(":au", $au);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================
    // CREATION DU RAPPORT EXCEL
    // ============================================

    $objPHPExcel = new Spreadsheet();
    $objPHPExcel->setActiveSheetIndex(0);
    $newsheet = $objPHPExcel->getActiveSheet();
    $newsheet->setTitle("SYNTHESE");

    // Logo et en-tête sur la feuille SYNTHESE
    addLogoToSheet($newsheet);
    addHeaderToSheet($newsheet);

    foreach ($liste_site as $site_item) {
        $site_classe->code_site = $site_item;
        $site_classe->GetDetailIN();
        $USER_SITENAME = $site_classe->intitule_site;

        $cvs_list = $cls_report->GetAll_Site_CVSList($site_item);

        // Filtrer les CVS si sélectionnés
        if (!in_array('ALL', $selected_cvs) && !empty($selected_cvs)) {
            $cvs_list = array_filter($cvs_list, function ($cvs_item) use ($selected_cvs) {
                return in_array($cvs_item['code'], $selected_cvs);
            });
        }
        $cvs_list = array_values($cvs_list);

        $rowNumber = 7; // Début à la ligne 7 pour laisser place au logo

        // TITRE RAPPORT
        $newsheet->mergeCells('B' . $rowNumber . ':H' . $rowNumber);
        cellStyle($newsheet, 'B' . $rowNumber, 14);
        $newsheet->setCellValue('B' . $rowNumber, 'RAPPORT DES ASSIGNATIONS - ' . $USER_SITENAME . ' du ' . $du_ . ' au ' . $au_);
        cellBorder($newsheet, 'B' . $rowNumber . ':H' . $rowNumber);
        cellAlign($newsheet, 'B' . $rowNumber, 'C');
        cellColor($newsheet, 'B' . $rowNumber, 'b6b8b9');

        // ============================================
        // TABLEAU DE SYNTHESE PAR CVS
        // ============================================
        $rowNumber += 2;
        cellStyle($newsheet, 'A' . $rowNumber, 13);
        $newsheet->setCellValue('A' . $rowNumber, 'Synthèse par CVS');

        $rowNumber += 2;
        $Total_assignations = 0;
        $Total_controles = 0;
        $Total_non_conformites = 0;
        $col = 'A';
        $rowNumber_titre = $rowNumber;
        $rowNumber_assign = $rowNumber + 1;
        $rowNumber_control = $rowNumber + 2;
        $rowNumber_nonconf = $rowNumber + 3;
        $rowNumber_taux = $rowNumber + 4;

        // En-têtes
        $newsheet->setCellValue($col . $rowNumber_assign, 'Assignations');
        cellBorder($newsheet, $col . $rowNumber_assign);
        cellStyle($newsheet, $col . $rowNumber_assign, 10);
        cellColor($newsheet, $col . $rowNumber_assign, 'D6E4F0');
        cellAlign($newsheet, $col . $rowNumber_assign, 'C');

        $newsheet->setCellValue($col . $rowNumber_control, 'Contrôles');
        cellBorder($newsheet, $col . $rowNumber_control);
        cellStyle($newsheet, $col . $rowNumber_control, 10);
        cellColor($newsheet, $col . $rowNumber_control, 'D6E4F0');
        cellAlign($newsheet, $col . $rowNumber_control, 'C');

        $newsheet->setCellValue($col . $rowNumber_nonconf, 'Non-conformités');
        cellBorder($newsheet, $col . $rowNumber_nonconf);
        cellStyle($newsheet, $col . $rowNumber_nonconf, 10);
        cellColor($newsheet, $col . $rowNumber_nonconf, 'D6E4F0');
        cellAlign($newsheet, $col . $rowNumber_nonconf, 'C');

        $newsheet->setCellValue($col . $rowNumber_taux, 'Taux (%)');
        cellBorder($newsheet, $col . $rowNumber_taux);
        cellStyle($newsheet, $col . $rowNumber_taux, 10);
        cellColor($newsheet, $col . $rowNumber_taux, 'D6E4F0');
        cellAlign($newsheet, $col . $rowNumber_taux, 'C');

        $col = 'B';
        foreach ($cvs_list as $cvs_item) {
            $data = getAssignationData($db, $cvs_item["code"], $du, $au);
            
            $total_assign = 0;
            $total_control = 0;
            $total_nonconf = 0;
            
            foreach ($data as $row) {
                $total_assign += $row['assignations_recues'];
                $total_control += $row['controles_realises'];
                $total_nonconf += $row['non_conformites'];
            }
            
            $taux = $total_assign > 0 ? round(($total_control / $total_assign) * 100, 1) : 0;
            
            $Total_assignations += $total_assign;
            $Total_controles += $total_control;
            $Total_non_conformites += $total_nonconf;

            $newsheet->setCellValue($col . $rowNumber_titre, 'CVS/' . $cvs_item["libelle"]);
            cellBorder($newsheet, $col . $rowNumber_titre);
            cellStyle($newsheet, $col . $rowNumber_titre, 10);
            cellColor($newsheet, $col . $rowNumber_titre, 'E8E8E8');

            $newsheet->setCellValue($col . $rowNumber_assign, $total_assign);
            cellBorder($newsheet, $col . $rowNumber_assign);
            cellAlign($newsheet, $col . $rowNumber_assign, 'C');

            $newsheet->setCellValue($col . $rowNumber_control, $total_control);
            cellBorder($newsheet, $col . $rowNumber_control);
            cellAlign($newsheet, $col . $rowNumber_control, 'C');

            $newsheet->setCellValue($col . $rowNumber_nonconf, $total_nonconf);
            cellBorder($newsheet, $col . $rowNumber_nonconf);
            cellAlign($newsheet, $col . $rowNumber_nonconf, 'C');

            $newsheet->setCellValue($col . $rowNumber_taux, $taux . '%');
            cellBorder($newsheet, $col . $rowNumber_taux);
            cellAlign($newsheet, $col . $rowNumber_taux, 'C');
            
            // Colorer en rouge si taux < 70%
            if ($taux < 70) {
                cellColor($newsheet, $col . $rowNumber_taux, 'FFCCCC');
            }

            $col++;
        }

        // Total général
        $taux_general = $Total_assignations > 0 ? round(($Total_controles / $Total_assignations) * 100, 1) : 0;

        $newsheet->setCellValue($col . $rowNumber_titre, 'TOTAL');
        cellBorder($newsheet, $col . $rowNumber_titre);
        cellStyle($newsheet, $col . $rowNumber_titre, 10);
        cellColor($newsheet, $col . $rowNumber_titre, '4472C4');
        cellColorFont($newsheet, $col . $rowNumber_titre, 'FFFFFF');

        $newsheet->setCellValue($col . $rowNumber_assign, $Total_assignations);
        cellBorder($newsheet, $col . $rowNumber_assign);
        cellAlign($newsheet, $col . $rowNumber_assign, 'C');
        cellStyle($newsheet, $col . $rowNumber_assign, 10);

        $newsheet->setCellValue($col . $rowNumber_control, $Total_controles);
        cellBorder($newsheet, $col . $rowNumber_control);
        cellAlign($newsheet, $col . $rowNumber_control, 'C');
        cellStyle($newsheet, $col . $rowNumber_control, 10);

        $newsheet->setCellValue($col . $rowNumber_nonconf, $Total_non_conformites);
        cellBorder($newsheet, $col . $rowNumber_nonconf);
        cellAlign($newsheet, $col . $rowNumber_nonconf, 'C');
        cellStyle($newsheet, $col . $rowNumber_nonconf, 10);

        $newsheet->setCellValue($col . $rowNumber_taux, $taux_general . '%');
        cellBorder($newsheet, $col . $rowNumber_taux);
        cellAlign($newsheet, $col . $rowNumber_taux, 'C');
        cellStyle($newsheet, $col . $rowNumber_taux, 10);
        
        if ($taux_general < 70) {
            cellColor($newsheet, $col . $rowNumber_taux, 'FFCCCC');
        }

        // ============================================
        // DETAIL PAR CVS (Feuilles séparées)
        // ============================================
        foreach ($cvs_list as $cvs_item) {
            $data = getAssignationData($db, $cvs_item["code"], $du, $au);
            
            if (count($data) > 0) {
                // Créer une nouvelle feuille pour chaque CVS
                $newsheet_detail = $objPHPExcel->createSheet();
                $sheetTitle = sanitizeSheetTitle($cvs_item["libelle"] . '_Assignations');
                $newsheet_detail->setTitle($sheetTitle);
                
                // Ajouter le logo et l'en-tête sur la feuille détaillée
                addLogoToSheet($newsheet_detail);
                addHeaderToSheet($newsheet_detail);
                
                $rowNumberDetail = 7; // Commencer à la ligne 7 pour laisser place au logo
                
                // Titre du CVS
                $newsheet_detail->mergeCells('A' . $rowNumberDetail . ':H' . $rowNumberDetail);
                cellStyle($newsheet_detail, 'A' . $rowNumberDetail, 14);
                $newsheet_detail->setCellValue('A' . $rowNumberDetail, 'RAPPORT DES ASSIGNATIONS - CVS ' . $cvs_item["libelle"] . ' du ' . $du_ . ' au ' . $au_);
                cellAlign($newsheet_detail, 'A' . $rowNumberDetail, 'C');
                cellColor($newsheet_detail, 'A' . $rowNumberDetail, '4472C4');
                cellColorFont($newsheet_detail, 'A' . $rowNumberDetail, 'FFFFFF');
                
                $rowNumberDetail += 2;
                
                // En-têtes des colonnes
                $headers = ['Date', 'Technicien', 'CVS', 'Assignations reçues', 'Contrôles réalisés', 'En attente', 'Taux de réalisation (%)', 'Non-conformités'];
                $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                
                foreach ($headers as $index => $header) {
                    $cell = $cols[$index] . $rowNumberDetail;
                    $newsheet_detail->setCellValue($cell, $header);
                    cellBorderAll($newsheet_detail, $cell);
                    cellStyle($newsheet_detail, $cell, 10);
                    cellColor($newsheet_detail, $cell, 'D6E4F0');
                    cellAlign($newsheet_detail, $cell, 'C');
                }
                $rowNumberDetail++;
                
                // Remplir les données
                foreach ($data as $row) {
                    $newsheet_detail->setCellValue('A' . $rowNumberDetail, $row['date_assignation']);
                    $newsheet_detail->setCellValue('B' . $rowNumberDetail, $row['technicien']);
                    $newsheet_detail->setCellValue('C' . $rowNumberDetail, $row['cvs']);
                    $newsheet_detail->setCellValue('D' . $rowNumberDetail, $row['assignations_recues']);
                    $newsheet_detail->setCellValue('E' . $rowNumberDetail, $row['controles_realises']);
                    $newsheet_detail->setCellValue('F' . $rowNumberDetail, $row['en_attente']);
                    $newsheet_detail->setCellValue('G' . $rowNumberDetail, $row['taux_realisation']);
                    $newsheet_detail->setCellValue('H' . $rowNumberDetail, $row['non_conformites']);
                    
                    for ($i = 0; $i < 8; $i++) {
                        cellBorderAll($newsheet_detail, $cols[$i] . $rowNumberDetail);
                        cellAlign($newsheet_detail, $cols[$i] . $rowNumberDetail, 'C');
                    }
                    
                    // Colorer en rouge si taux < 70%
                    if ($row['taux_realisation'] < 70) {
                        for ($i = 0; $i < 8; $i++) {
                            cellColor($newsheet_detail, $cols[$i] . $rowNumberDetail, 'FFCCCC');
                        }
                    }
                    
                    $rowNumberDetail++;
                }
                
                // SYNTHESE PAR TECHNICIEN
                $rowNumberDetail++;
                $newsheet_detail->mergeCells('A' . $rowNumberDetail . ':E' . $rowNumberDetail);
                $newsheet_detail->setCellValue('A' . $rowNumberDetail, 'SYNTHÈSE PAR TECHNICIEN');
                cellStyle($newsheet_detail, 'A' . $rowNumberDetail, 12);
                cellColor($newsheet_detail, 'A' . $rowNumberDetail, '70AD47');
                cellColorFont($newsheet_detail, 'A' . $rowNumberDetail, 'FFFFFF');
                $rowNumberDetail++;

                // En-têtes synthèse
                $headers_synth = ['Technicien', 'Assignations', 'Contrôles', 'En attente', 'Taux (%)'];
                $cols_synth = ['A', 'B', 'C', 'D', 'E'];
                
                foreach ($headers_synth as $index => $header) {
                    $cell = $cols_synth[$index] . $rowNumberDetail;
                    $newsheet_detail->setCellValue($cell, $header);
                    cellBorderAll($newsheet_detail, $cell);
                    cellStyle($newsheet_detail, $cell, 10);
                    cellColor($newsheet_detail, $cell, 'D6E4F0');
                    cellAlign($newsheet_detail, $cell, 'C');
                }
                $rowNumberDetail++;

                // Récupérer la synthèse par technicien
                $synth_data = getSynthByTechnicien($db, $cvs_item["code"], $du, $au);
                
                if (!empty($synth_data)) {
                    foreach ($synth_data as $row) {
                        $newsheet_detail->setCellValue('A' . $rowNumberDetail, $row['technicien']);
                        $newsheet_detail->setCellValue('B' . $rowNumberDetail, $row['assignations']);
                        $newsheet_detail->setCellValue('C' . $rowNumberDetail, $row['controles']);
                        $newsheet_detail->setCellValue('D' . $rowNumberDetail, $row['en_attente']);
                        $newsheet_detail->setCellValue('E' . $rowNumberDetail, $row['taux'] . '%');
                        
                        for ($i = 0; $i < 5; $i++) {
                            cellBorderAll($newsheet_detail, $cols_synth[$i] . $rowNumberDetail);
                            cellAlign($newsheet_detail, $cols_synth[$i] . $rowNumberDetail, 'C');
                        }
                        $rowNumberDetail++;
                    }
                }
                
                // Ajuster la largeur des colonnes
                foreach (range('A', 'H') as $col) {
                    $newsheet_detail->getColumnDimension($col)->setAutoSize(true);
                }
            }
        }
    }

    // Ajuster la largeur des colonnes sur la feuille synthèse
    foreach (range('A', 'H') as $col) {
        $newsheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Supprimer la feuille par défaut si elle est vide
    $defaultSheet = $objPHPExcel->getSheet(0);
    if ($defaultSheet->getTitle() == 'Worksheet') {
        $objPHPExcel->removeSheetByIndex(0);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="rapport_assignation.xlsx"');
    header('Cache-Control: max-age=0');

    $objWriter = new Xlsx($objPHPExcel);
    $objWriter->save('php://output');
    exit();

} catch (Exception $exception) {
    echo "An error occurred: " . $exception->getMessage();
}
?>