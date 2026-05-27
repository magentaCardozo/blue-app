<?php
require_once 'vendor/autoload.php';
require_once 'loader/init.php';
Autoloader::Load('classes');
include_once "core.php";

$view = "";
$result_array = array();

$database = new Database();
$db = $database->getConnection();

$utilisateur = new Utilisateur($db);

if (isset($_REQUEST["view"]))
	$view = $_REQUEST["view"];


// 🔐 Vérification login
if ($utilisateur->is_logged_in() == false) {
	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
		&& strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {

		$result_array["error"] = 1;
		$result_array["reconnect"] = true;
		$result_array["message"] = "Vous devriez vous reconnecter";
		echo json_encode($result_array);
		exit;
	} else {
		$utilisateur->redirect('login.php');
	}
}

$utilisateur->readOne();


// =========================
// SWITCH
// =========================
switch ($view) {

	// =========================
	// CREATE
	// =========================
	case "create_code":

		$item = new Param_MotifEnCours($db);

		$item->code = isset($_POST["code"]) ? $_POST["code"] : "";
		$item->libelle = isset($_POST["libelle"]) ? $_POST["libelle"] : "";
		$item->n_user_create = $utilisateur->code_utilisateur;

		$result_array = $item->Create();

		Utils::responseJson($result_array);
	break;


	// =========================
	// UPDATE
	// =========================
	case "edit_code":

		$item = new Param_MotifEnCours($db);

		$item->code = isset($_POST["code"]) ? $_POST["code"] : "";
		$item->libelle = isset($_POST["libelle"]) ? $_POST["libelle"] : "";
		$item->n_user_update = $utilisateur->code_utilisateur;

		$result_array = $item->Modifier();

		Utils::responseJson($result_array);
	break;


	// =========================
	// DELETE
	// =========================
	case "delete_code":

		$item = new Param_MotifEnCours($db);

		$item->code = isset($_POST["code"]) ? $_POST["code"] : "";
		$item->n_user_annule = $utilisateur->code_utilisateur;

		$result_array = $item->Supprimer();

		Utils::responseJson($result_array);
	break;


	// =========================
	// DEFAULT
	// =========================
	default:
		$result_array["error"] = 1;
		$result_array["message"] = "Requête non prise en charge";
		echo json_encode($result_array);
	break;
}

$db = null;