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

$database = new Database();
$db = $database->getConnection();
$utilisateur = new Utilisateur($db);
//$droits = new Droits();
$cls_report = new CLS_Reporting($db);
$Installation = new Installation($db);
$adresseItem = new AdresseEntity($db);
$cvs = new CVS($db);
$marquecompteur = new MarqueCompteur($db);
$typeFraude = new PARAM_TypeFraude($db);
$item_ctl = new CLS_Controle($db);

$site_classe = new Site($db);
$liste_site = array();
$header_bg_color = "#ECF2FE"; // "#5969ff";//eee6ff
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

// Titre de feuille Excel valide : 31 caractères max, sans \ / ? * [ ] : (ex. CVS "DELVAUX/Ngaliema"), et unique
$sheet_titles_used = array();
function uniqueSheetTitle(&$used, $title)
{
	// Excel interdit aussi l'apostrophe en début / fin de titre
	$title = trim(str_replace(array('\\', '/', '?', '*', '[', ']', ':'), '_', (string)$title), "'");
	if (trim($title) === '') {
		$title = 'CVS';
	}
	$base = mb_substr($title, 0, 31);
	$title = $base;
	$n = 2;
	while (isset($used[mb_strtolower($title)])) {
		$suffix = ' (' . $n++ . ')';
		$title = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
	}
	$used[mb_strtolower($title)] = true;
	return $title;
}

// Caches des libellés (évite une requête par ligne)
$cache_labels = array();
function cachedLabel(&$cache, $type, $code, $loader)
{
	$code = (string)$code;
	if (!isset($cache[$type][$code])) {
		$cache[$type][$code] = $code === '' ? '' : (string)$loader($code);
	}
	return $cache[$type][$code];
}

if (in_array($MULTI_ACCESS_SITE_CODE, $site)) {
	$liste_site =  $cls_report->GetAll_AccessibleUSerSite($utilisateur->code_utilisateur);
} else {
	$liste_site = $site;
}

$ctr_cvs = 0;
$objPHPExcel = new Spreadsheet();



foreach ($liste_site as $site_item) {
	$site_classe->code_site = $site_item;
	$site_classe->GetDetailIN();
	//$USER_SITENAME = $site_classe->intitule_site;

	$cvs_list = $cls_report->GetAll_Site_CVSList($site_item);
	$count_cvs = count($cvs_list);
	$ctr_cvs = 0;
	$start_l = false;
	$rowNumber = 7; //start in row 1



	$objPHPExcel->setActiveSheetIndex(0);
	$newsheet =   $objPHPExcel->getActiveSheet();
	//DEBUT SYNTHESE

	$newsheet->setTitle("SYNTHESE");
	$newsheet->mergeCells('B' . $rowNumber . ':H' . $rowNumber);
	cellStyle($newsheet, 'B' . $rowNumber, 14);
	$newsheet->setCellValue('B' . $rowNumber, 'TABLEAU RESUME  du ' . $du_ . ' au ' . $au_);
	cellBorder($newsheet, 'B' . $rowNumber . ':H' . $rowNumber);
	cellAlign($newsheet, 'B' . $rowNumber, 'C');
	cellColor($newsheet, 'B' . $rowNumber, 'b6b8b9');
	// cellColor($newsheet,'B'.$rowNumber, '6c757d');


	//////////////////CONTROLES FRAUDE/////////////////////////
	$rowNumber++;
	$rowNumber++;
	cellStyle($newsheet, 'A' . $rowNumber, 13);
	$newsheet->setCellValue('A' . $rowNumber, 'Compteurs contrôlés en Etat de Fraude');
	$rowNumber++;
	$rowNumber++;
	$Total_sceller = 0;
	$Total_install = 0;
	$col = 'A';
	$rowNumber_titre = $rowNumber;
	$rowNumber_install = $rowNumber + 1;


	$newsheet->setCellValue($col . $rowNumber_install, 'Fraudes');
	cellBorder($newsheet, $col . $rowNumber_install);

	$col = 'B';
	foreach ($cvs_list as $cvs_item) {
		$nbre_install = $cls_report->getCVS_CompteursFraudePeriodeCount($cvs_item["code"], $du, $au);

		$Total_sceller += $nbre_install;

		$newsheet->setCellValue($col . $rowNumber_titre, 'CVS/' . $cvs_item["libelle"]);
		$newsheet->getColumnDimension($col)->setAutoSize(true);
		cellBorder($newsheet, $col . $rowNumber_titre);
		cellStyle($newsheet, $col . $rowNumber_titre, 10);

		$newsheet->setCellValue($col . $rowNumber_install, $nbre_install . ' ');
		cellBorder($newsheet, $col . $rowNumber_install);

		$col++;
	}
	// Total_install

	$newsheet->setCellValue($col . $rowNumber_titre, 'TOTAL');
	cellBorder($newsheet, $col . $rowNumber_titre);
	cellStyle($newsheet, $col . $rowNumber_titre, 10);

	$newsheet->setCellValue($col . $rowNumber_install, $Total_sceller . ' ');
	cellBorder($newsheet, $col . $rowNumber_install);


	$rowNumber = $rowNumber + 3;

	//////////////////END CONTROLE FRAUDE /////////////////////////


}
//FIN SYNTHESE  

$headers_ = array("", "Quartier", "CVS", "Adresse (Avenue et N°)", "Noms et Postnoms", "PA (POC)", "Tarif", "Marque", "Numéro de compteur", "Date installation", "N° Scellé cpt 1", "N° Scellé coffret 2", "Date de pose scellé", "Scellé compteur brisé 1", "Scellé coffret brisé 2", "Scellé cpt existant 1", "Scellé coffret existant 2", "Numéro série compteur trouvé", "Etat de fraude", "Raison de la fraude", "Etat du compteur", "Date de dernier ticket rentré", "Qté des derniers Kwh rentrés", "Crédit restant", "Tarif contrôle", "Autocollant placé (Contrôleur)");
foreach ($liste_site as $site_item) {
	$site_classe->code_site = $site_item;
	$site_classe->GetDetailIN();
	//$USER_SITENAME = $site_classe->intitule_site;	
	$cvs_list = $cls_report->GetAll_Site_CVSList($site_item);
	$count_cvs = count($cvs_list);
	$start_l = false;
	// echo $site_classe->intitule_site; 
	//echo 'Date impression:  ' . date('m/d/Y');  
	// echo 'Liste des compteurs contrôlés du '. $du_ .' au '. $au_; 
	//ITERATION DATA IINSTALL FOR CURRENT SITE
	// Lignes de toutes les CVS du site, puis leurs fraudes en quelques requêtes
	$data_par_cvs = array();
	$refs_controle = array();
	foreach ($cvs_list as $cvs_item) {
		$data_par_cvs[$cvs_item["code"]] = $cls_report->getCVS_CompteursFraude($cvs_item["code"], $du, $au);
		foreach ($data_par_cvs[$cvs_item["code"]] as $r_) {
			$refs_controle[] = $r_["ref_fiche_controle"];
		}
	}
	$fraudes_obs = $cls_report->GetFraudesObservationsControles($refs_controle);

	foreach ($cvs_list as $cvs_item) {

		//RECUPERATION LISTE DES COMPTEURS CONTROLES POUR LE CURRENT CVS AND GIVEN PERIOD 
		$data_ = $data_par_cvs[$cvs_item["code"]];
		$nb_data_ = count($data_);
		if ($nb_data_ > 0) {

			$newsheet = $objPHPExcel->createSheet();



			// Rename worksheet

			$newsheet->setTitle(uniqueSheetTitle($sheet_titles_used, $cvs_item["libelle"]));

			$rowNumber = 1; //start in row 1

			$col = 'A'; // start at column A
			$newsheet->setCellValue("A1", "LISTE DE COMPTEURS EN ETAT DE FRAUDE  ( CVS " . $cvs_item["libelle"] . ') ' . count($data_) . " Compteurs");
			cellStyle($newsheet, 'A1', 14);
			$cell = 'Période : du ' . $du_ . ' au ' . $au_;
			$rowNumber++;
			$newsheet->setCellValue($col . $rowNumber, $cell);
			cellStyle($newsheet, $col . $rowNumber, 13);
			$rowNumber++;
			$rowNumber++;


			$newsheet->getColumnDimension('B')->setAutoSize(true); // Content adaptation 
			$newsheet->getColumnDimension('C')->setAutoSize(true); // Content adaptation 
			$newsheet->getColumnDimension('D')->setAutoSize(true); // Content adaptation 
			$col = 'A'; // start at column A
			foreach ($headers_ as $cell) {
				$newsheet->setCellValue($col . $rowNumber, $cell);
				$col++;
			}
			$ctr_context = 1;

			//ENTETES PRINCIPALES

			cellStyle($newsheet, 'A' . $rowNumber, 10);
			$newsheet->setCellValue('A' . $rowNumber, "N°");
			cellAlign($newsheet, 'A' . $rowNumber, 'C');
			cellColor($newsheet, 'A' . $rowNumber, 'ffc107');


			$colons = array('B', 'C', 'D', 'E', 'F', 'G');
			foreach ($colons as $mycol) {
				cellBorder($newsheet, $mycol . $rowNumber);
				cellStyle($newsheet, $mycol . $rowNumber, 10);
				cellAlign($newsheet, $mycol . $rowNumber, 'C');
				cellColor($newsheet, $mycol . $rowNumber, '17a2b8');
				$newsheet->getColumnDimension($mycol)->setAutoSize(true); // Content adaptation 
			}


			$colons = array('H', 'I', 'J', 'K', 'L', 'M');
			foreach ($colons as $mycol) {
				cellBorder($newsheet, $mycol . $rowNumber);
				cellStyle($newsheet, $mycol . $rowNumber, 10);
				cellAlign($newsheet, $mycol . $rowNumber, 'C');
				cellColor($newsheet, $mycol . $rowNumber, 'ffc107');

				$newsheet->getColumnDimension($mycol)->setAutoSize(true); // Content adaptation 
			}

			$colons = array('N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
			foreach ($colons as $mycol) {
				cellBorder($newsheet, $mycol . $rowNumber);
				cellStyle($newsheet, $mycol . $rowNumber, 10);
				cellAlign($newsheet, $mycol . $rowNumber, 'C');
				cellColor($newsheet, $mycol . $rowNumber, '007bff');
				$newsheet->getColumnDimension($mycol)->setAutoSize(true); // Content adaptation 
			}



			//end ENTETES PRINCIPALES
			$rowNumber++;



			/* <h5 class="mb-3">CVS :</h5>                                            
                                            <h4 class="text-dark mb-1"><?php echo $cvs_item["libelle"]; ?></h4>*/

			// $rowNumber = 1; //start in row 1

			$col = 'B'; // start at column A 
			$startDataRow = $rowNumber;

			foreach ($data_ as $row_) {
				$col = 'A'; // start at column A

				$cvs_libelle = cachedLabel($cache_labels, 'cvs', $row_["cvs_id"], function ($c) use ($cvs) {
					$cvs->code = $c;
					$cvs->GetDetailIN();
					return $cvs->libelle;
				});
				$E_item = $adresseItem->GetAdressInfo($row_["adresse_id"]);
				if (!is_array($E_item)) {
					$E_item = array();
				}
				$numero =  $E_item['numero'] ?? '';
				$avenue =  cachedLabel($cache_labels, 'entite', $E_item['avenue'] ?? '', array($adresseItem, 'GetLabel'));
				$quartier_ = cachedLabel($cache_labels, 'entite', $E_item['quartier_id'] ?? '', array($adresseItem, 'GetLabel'));

				// $newsheet->setCellValue($col.$rowNumber,$ctr_context);
				$newsheet->setCellValue($col . $rowNumber, $row_["ref_fiche_controle"]);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $quartier_);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $cvs_libelle);
				$col++;


				$newsheet->setCellValue($col . $rowNumber, $avenue . ' ' . $numero);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_["nom_client_blue"]);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_["p_a"]);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_["tarif_identif"]);
				$col++;



				//RECUPERATION INFOS FOUND INSTALL DURING CONTROLES
				$row_install = $Installation->GetDetail_Light($row_["ref_last_install_found"]);
				if (!is_array($row_install)) {
					// Contrôle sans installation retrouvée : colonnes installation vides (au lieu d'avertissements PHP)
					$row_install = array();
				}
				$marque_libelle = cachedLabel($cache_labels, 'marque', $row_install["marque_compteur"] ?? '', function ($c) use ($marquecompteur) {
					$marquecompteur->code = $c;
					$marquecompteur->GetDetailIN();
					return $marquecompteur->libelle;
				});



				$newsheet->setCellValue($col . $rowNumber, $marque_libelle);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_install["numero_compteur"] ?? null);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_install["date_fin_installation_fr"] ?? null);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_install["scelle_un_cpteur"] ?? null);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_install["scelle_deux_coffret"] ?? null);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_install["date_pose_scelle_fr"] ?? null);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_["scelle_compteur_poser"]);
				$col++;


				$newsheet->setCellValue($col . $rowNumber, $row_["scelle_coffret_poser"]);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_["scelle_cpt_existant"]);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_["scelle_coffret_existant"]);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_["numero_serie_cpteur"]);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_["cas_de_fraude"]);
				$col++;


				$labelFraude = function ($c) use ($typeFraude) {
					$typeFraude->code = $c;
					$typeFraude->GetDetailIN();
					return $typeFraude->code_label;
				};

				$lst_fraudes_selected = "";
				//COMPATIBILITY MODE	
				if (!empty($row_["type_fraude"])) {
					$lst_fraudes_selected .= cachedLabel($cache_labels, 'fraude', $row_['type_fraude'], $labelFraude) . "-";
				}
				foreach (($fraudes_obs['fraudes'][$row_["ref_fiche_controle"]] ?? array()) as $code_) {
					$lst_fraudes_selected .= cachedLabel($cache_labels, 'fraude', $code_, $labelFraude) . "-";
				}
				$type_fraudes =  rtrim($lst_fraudes_selected, "-");


				/*	$typeFraude->code=$row_["type_fraude"];
			$typeFraude->GetDetailIN();*/


				// $newsheet->setCellValue($col.$rowNumber,$typeFraude->libelle);
				$newsheet->setCellValue($col . $rowNumber, $type_fraudes . " ");
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_["etat_du_compteur"]);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_["date_de_dernier_ticket_rentre"]);
				$col++;


				$newsheet->setCellValue($col . $rowNumber, $row_["qte_derniers_kwh_rentre"]);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_["credit_restant"]);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_["tarif_controle"]);
				$col++;

				$newsheet->setCellValue($col . $rowNumber, $row_["autocollant_place_controleur"]);
				$col++;

				/*$newsheet->setCellValue($col.$rowNumber,$row_["observation"]);
				cellBorder($newsheet,$col.$rowNumber);
				$col++; */


				$rowNumber++;

				$ctr_context++;
			}
			// Bordures appliquées en une fois sur les lignes de la feuille (colonnes A à Z)
			if ($rowNumber > $startDataRow) {
				$newsheet->getStyle('A' . $startDataRow . ':Z' . ($rowNumber - 1))->applyFromArray([
					'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
				]);
			}
		}
	}
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="rapport_etat_de_fraude.xlsx"');
header('Cache-Control: max-age=0');

$objWriter = new Xlsx($objPHPExcel);
$objWriter->save('php://output');
exit();
