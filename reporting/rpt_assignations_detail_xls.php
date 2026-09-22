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

$controleur = isset($_POST['controleur']) ? trim($_POST['controleur']) : "";
$createur = isset($_POST['createur']) ? trim($_POST['createur']) : "";
$du = isset($_POST['Du']) ? Utils::ClientToDbDateFormat($_POST['Du']) : "";
$au = isset($_POST['Au']) ? Utils::ClientToDbDateFormat($_POST['Au']) : "";
$du_ = isset($_POST['Du']) ? ($_POST['Du']) : "";
$au_ = isset($_POST['Au']) ? ($_POST['Au']) : "";

if ($utilisateur->is_logged_in() == false) {
    $utilisateur->redirect('../login.php');
}
$utilisateur->readOne();

try {
    // ============================================
    // CONSTRUCTION DU FILTRE (identique à la liste lst_assign_control.php)
    // ============================================
    $conditions = "t_main_data.ref_site_identif=:ref_site_identif
			and t_param_assignation.annule=0
			and t_param_assignation.type_assignation=:type_assignation" . $utilisateur->GetUserFilterAssignation();
    $params = array(
        ":type_assignation" => '1',
        ":ref_site_identif" => $utilisateur->site_id
    );

    if ($controleur != "") {
        $conditions .= " and t_param_assignation.id_technicien=:controleur";
        $params[":controleur"] = $controleur;
    }
    if ($createur != "") {
        $conditions .= " and t_param_assignation.n_user_create=:createur";
        $params[":createur"] = $createur;
    }
    if ($du != "" and $au != "") {
        $conditions .= " and (DATE_FORMAT(t_param_assignation.datesys,'%Y-%m-%d') between :du_assign and :au_assign)";
        $params[":du_assign"] = $du;
        $params[":au_assign"] = $au;
    }

    $query = "SELECT COALESCE(t_tech.nom_complet, t_controleur.nom_complet, NULLIF(t_param_assignation.id_technicien,''), '-') as nom_controlleur,
            t_main_data.num_compteur_actuel as num_compteur,            COALESCE(ucr.nom_complet, NULLIF(t_param_assignation.n_user_create, ''), DATE_FORMAT(t_param_assignation.datesys, '%d/%m/%Y')) as nom_createur,
            DATE_FORMAT(t_param_assignation.datesys, '%d/%m/%Y %H:%i') as date_assignation
        FROM t_main_data
        INNER JOIN t_param_assignation ON t_main_data.id_ = t_param_assignation.id_fiche_identif
        LEFT JOIN t_utilisateurs AS t_tech ON t_param_assignation.id_technicien = t_tech.code_utilisateur
        LEFT JOIN t_utilisateurs AS t_controleur ON t_param_assignation.id_controleur_quality = t_controleur.code_utilisateur
        LEFT JOIN t_utilisateurs AS ucr ON t_param_assignation.n_user_create = ucr.code_utilisateur
        WHERE " . $conditions . "
        ORDER BY nom_createur, nom_controlleur, t_param_assignation.datesys DESC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            $path = __DIR__ . '/images/logo2.png';
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

    // ============================================
    // CREATION DU RAPPORT EXCEL
    // ============================================

    $objPHPExcel = new Spreadsheet();
    $objPHPExcel->setActiveSheetIndex(0);
    $newsheet = $objPHPExcel->getActiveSheet();
    $newsheet->setTitle("ASSIGNATIONS CONTROLE");

    addLogoToSheet($newsheet);

    $rowNumber = 7;

    // TITRE DU RAPPORT
    $newsheet->mergeCells('A' . $rowNumber . ':D' . $rowNumber);
    cellStyle($newsheet, 'A' . $rowNumber, 14);
    $titre = 'ASSIGNATIONS POUR CONTROLE';
    if ($du_ != '' or $au_ != '') {
        $titre .= ' du ' . $du_ . ' au ' . $au_;
    }
    $newsheet->setCellValue('A' . $rowNumber, $titre);
    cellBorderAll($newsheet, 'A' . $rowNumber . ':D' . $rowNumber);
    cellAlign($newsheet, 'A' . $rowNumber, 'C');
    cellColor($newsheet, 'A' . $rowNumber, 'b6b8b9');

    $rowNumber += 2;

    // EN-TETES DES COLONNES
    $headers = ['Nom contrôleur', 'Numéro compteur', 'Assigné par', 'Date assignation'];
    $cols = ['A', 'B', 'C', 'D'];

    foreach ($headers as $index => $header) {
        $cell = $cols[$index] . $rowNumber;
        $newsheet->setCellValue($cell, $header);
        cellBorderAll($newsheet, $cell);
        cellStyle($newsheet, $cell, 11);
        cellColor($newsheet, $cell, 'D6E4F0');
        cellAlign($newsheet, $cell, 'C');
    }
    $rowNumber++;

    // DONNEES
    if (count($data) > 0) {
        foreach ($data as $row) {
            $newsheet->setCellValue('A' . $rowNumber, $row['nom_controlleur']);
            $newsheet->setCellValue('B' . $rowNumber, $row['num_compteur']);
            $newsheet->setCellValue('C' . $rowNumber, $row['nom_createur']);
            $newsheet->setCellValue('D' . $rowNumber, $row['date_assignation']);

            for ($i = 0; $i < 4; $i++) {
                cellBorderAll($newsheet, $cols[$i] . $rowNumber);
            }
            $rowNumber++;
        }
    } else {
        $newsheet->mergeCells('A' . $rowNumber . ':D' . $rowNumber);
        $newsheet->setCellValue('A' . $rowNumber, 'Aucune assignation trouvée pour les critères sélectionnés');
        cellBorderAll($newsheet, 'A' . $rowNumber . ':D' . $rowNumber);
        cellAlign($newsheet, 'A' . $rowNumber, 'C');
        $rowNumber++;
    }

    // TOTAL
    $newsheet->mergeCells('A' . $rowNumber . ':C' . $rowNumber);
    $newsheet->setCellValue('A' . $rowNumber, 'TOTAL');
    cellBorderAll($newsheet, 'A' . $rowNumber . ':C' . $rowNumber);
    cellStyle($newsheet, 'A' . $rowNumber, 10);
    cellColor($newsheet, 'A' . $rowNumber, '4472C4');
    cellColorFont($newsheet, 'A' . $rowNumber, 'FFFFFF');
    cellAlign($newsheet, 'A' . $rowNumber, 'C');
    $newsheet->setCellValue('D' . $rowNumber, count($data));
    cellBorderAll($newsheet, 'D' . $rowNumber);
    cellStyle($newsheet, 'D' . $rowNumber, 10);
    cellAlign($newsheet, 'D' . $rowNumber, 'C');

    // Ajuster la largeur des colonnes
    foreach (range('A', 'D') as $col) {
        $newsheet->getColumnDimension($col)->setAutoSize(true);
    }

    $file_name = 'assignations_controle';
    if ($du_ != '') {
        $file_name .= '_' . str_replace('/', '-', $du_);
    }
    if ($au_ != '') {
        $file_name .= '_' . str_replace('/', '-', $au_);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $file_name . '.xlsx"');
    header('Cache-Control: max-age=0');

    $objWriter = new Xlsx($objPHPExcel);
    $objWriter->save('php://output');
    exit();

} catch (Exception $exception) {
    echo "An error occurred: " . $exception->getMessage();
}
?>
