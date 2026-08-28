<?php
class Dashviewer
{

	// database connection and table name
	private $conn;
	private $Aborted = 1;
	private $Valid = 0;

	public function __construct($db)
	{
		$this->conn = $db;
	}

	function ClientToDbDateFormat($c_date)
	{
		$n_date = str_ireplace('/', '-', $c_date);
		$f_dt = date('Y-m-d', strtotime($n_date));
		return $f_dt;
	}

	function FormatNbre($nbre)
	{
		$f_dt = number_format($nbre, 0, ".", " ");
		return $f_dt;
	}

	function GetSite_CompteurIdentified($user_context, $site, $du, $au)
	{
		$user_filtre = $user_context->GetUserFilterIdentification();
		$site = htmlspecialchars(strip_tags($site));
		$du = htmlspecialchars(strip_tags($du));
		$au = htmlspecialchars(strip_tags($au));
		$query = "SELECT count(t_main_data.id_) as nbre
				  FROM t_main_data
				  WHERE (t_main_data.date_identification >= :du AND t_main_data.date_identification <= CONCAT(:au, ' 23:59:59'))
				  AND t_main_data.ref_site_identif = :site
				  AND t_main_data.annule = :annule
				  AND t_main_data.is_draft = '0'" . $user_filtre;
		$stmt = $this->conn->prepare($query);
		$stmt->bindParam(":site", $site);
		$stmt->bindParam(":du", $du);
		$stmt->bindParam(":au", $au);
		$stmt->bindParam(":annule", $this->Valid);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ? $row['nbre'] : 0;
	}

	function GetAll_CompteurIdentified($user_context, $du, $au)
	{
		$user_filtre = $user_context->GetUserFilterIdentification();
		$liste_site = array();
		$query = "SELECT t_param_site_production.code_site
				  FROM t_param_site_production
				  INNER JOIN t_utilisateur_site_accessible
				  ON t_param_site_production.code_site = t_utilisateur_site_accessible.code_site
				  WHERE t_utilisateur_site_accessible.code_user = :code_user";
		$code_user = strip_tags($user_context->code_utilisateur);
		$stmt = $this->conn->prepare($query);
		$stmt->bindValue(":code_user", $code_user);
		$stmt->execute();
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$liste_site[] = $row['code_site'];
		}

		if (empty($liste_site)) {
			return 0;
		}

		$liste_site_label = implode("','", $liste_site);
		$liste_site_label = "'" . $liste_site_label . "'";

		$du = htmlspecialchars(strip_tags($du));
		$au = htmlspecialchars(strip_tags($au));
		$query = "SELECT count(t_main_data.id_) as nbre
				  FROM t_main_data
				  WHERE (t_main_data.date_identification >= :du AND t_main_data.date_identification <= CONCAT(:au, ' 23:59:59'))
				  AND t_main_data.ref_site_identif IN (" . $liste_site_label . ")
				  AND t_main_data.annule = :annule
				  AND t_main_data.is_draft = '0'" . $user_filtre;
		$stmt = $this->conn->prepare($query);
		$stmt->bindParam(":du", $du);
		$stmt->bindParam(":au", $au);
		$stmt->bindParam(":annule", $this->Valid);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ? $row['nbre'] : 0;
	}

	function GetSite_CompteurInstalled($user_context, $site, $du, $au)
	{
		$user_filtre = $user_context->GetUserFilterInstallation();
		$site = strip_tags($site);
		$du = strip_tags($du);
		$au = strip_tags($au);
		$query = "SELECT count(t_log_installation.id_install) as nbre
				  FROM t_log_installation
				  WHERE (t_log_installation.date_fin_installation >= :du AND t_log_installation.date_fin_installation <= CONCAT(:au, ' 23:59:59'))
				  AND t_log_installation.ref_site_install = :site
				  AND t_log_installation.annule = :annule
				  AND t_log_installation.statut_installation = 1
				  AND t_log_installation.is_draft_install = 0
				  AND t_log_installation.approbation_installation = 1
				  AND t_log_installation.type_installation = '0'" . $user_filtre;
		$stmt = $this->conn->prepare($query);
		$stmt->bindParam(":site", $site);
		$stmt->bindParam(":du", $du);
		$stmt->bindParam(":au", $au);
		$stmt->bindParam(":annule", $this->Valid);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ? $row['nbre'] : 0;
	}

	function GetSite_CompteurReplaced($user_context, $site, $du, $au)
	{
		$user_filtre = $user_context->GetUserFilterInstallation();
		$site = strip_tags($site);
		$du = strip_tags($du);
		$au = strip_tags($au);
		$query = "SELECT count(t_log_installation.id_install) as nbre
				  FROM t_log_installation
				  WHERE (t_log_installation.date_fin_installation >= :du AND t_log_installation.date_fin_installation <= CONCAT(:au, ' 23:59:59'))
				  AND t_log_installation.ref_site_install = :site
				  AND t_log_installation.annule = :annule
				  AND t_log_installation.statut_installation = 1
				  AND t_log_installation.is_draft_install = 0
				  AND t_log_installation.approbation_installation = 1
				  AND t_log_installation.type_installation = '1'" . $user_filtre;
		$stmt = $this->conn->prepare($query);
		$stmt->bindParam(":site", $site);
		$stmt->bindParam(":du", $du);
		$stmt->bindParam(":au", $au);
		$stmt->bindParam(":annule", $this->Valid);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ? $row['nbre'] : 0;
	}

	function GetAll_CompteurInstalled($user_context, $du, $au)
	{
		$user_filtre = $user_context->GetUserFilterInstallation();
		$liste_site = array();
		$query = "SELECT t_param_site_production.code_site
				  FROM t_param_site_production
				  INNER JOIN t_utilisateur_site_accessible
				  ON t_param_site_production.code_site = t_utilisateur_site_accessible.code_site
				  WHERE t_utilisateur_site_accessible.code_user = :code_user";
		$code_user = strip_tags($user_context->code_utilisateur);
		$stmt = $this->conn->prepare($query);
		$stmt->bindValue(":code_user", $code_user);
		$stmt->execute();
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$liste_site[] = $row['code_site'];
		}

		if (empty($liste_site)) {
			return 0;
		}

		$liste_site_label = implode("','", $liste_site);
		$liste_site_label = "'" . $liste_site_label . "'";

		$du = strip_tags($du);
		$au = strip_tags($au);
		$query = "SELECT count(t_log_installation.id_install) as nbre
				  FROM t_log_installation
				  WHERE (t_log_installation.date_fin_installation >= :du AND t_log_installation.date_fin_installation <= CONCAT(:au, ' 23:59:59'))
				  AND t_log_installation.ref_site_install IN (" . $liste_site_label . ")
				  AND t_log_installation.annule = :annule
				  AND t_log_installation.statut_installation = 1
				  AND t_log_installation.is_draft_install = 0
				  AND t_log_installation.approbation_installation = 1
				  AND t_log_installation.type_installation = '0'" . $user_filtre;
		$stmt = $this->conn->prepare($query);
		$stmt->bindParam(":du", $du);
		$stmt->bindParam(":au", $au);
		$stmt->bindParam(":annule", $this->Valid);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ? $row['nbre'] : 0;
	}

	function GetAll_CompteurReplaced($user_context, $du, $au)
	{
		$user_filtre = $user_context->GetUserFilterInstallation();
		$liste_site = array();
		$query = "SELECT t_param_site_production.code_site
				  FROM t_param_site_production
				  INNER JOIN t_utilisateur_site_accessible
				  ON t_param_site_production.code_site = t_utilisateur_site_accessible.code_site
				  WHERE t_utilisateur_site_accessible.code_user = :code_user";
		$code_user = strip_tags($user_context->code_utilisateur);
		$stmt = $this->conn->prepare($query);
		$stmt->bindValue(":code_user", $code_user);
		$stmt->execute();
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$liste_site[] = $row['code_site'];
		}

		if (empty($liste_site)) {
			return 0;
		}

		$liste_site_label = implode("','", $liste_site);
		$liste_site_label = "'" . $liste_site_label . "'";

		$du = strip_tags($du);
		$au = strip_tags($au);
		$query = "SELECT count(t_log_installation.id_install) as nbre
				  FROM t_log_installation
				  WHERE (t_log_installation.date_fin_installation >= :du AND t_log_installation.date_fin_installation <= CONCAT(:au, ' 23:59:59'))
				  AND t_log_installation.ref_site_install IN (" . $liste_site_label . ")
				  AND t_log_installation.annule = :annule
				  AND t_log_installation.statut_installation = 1
				  AND t_log_installation.is_draft_install = 0
				  AND t_log_installation.approbation_installation = 1
				  AND t_log_installation.type_installation = '1'" . $user_filtre;
		$stmt = $this->conn->prepare($query);
		$stmt->bindParam(":du", $du);
		$stmt->bindParam(":au", $au);
		$stmt->bindParam(":annule", $this->Valid);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ? $row['nbre'] : 0;
	}

	function GetSite_CompteurControled($user_context, $site, $du, $au)
	{
		$user_filtre = $user_context->GetUserFilterControl();
		$site = strip_tags($site);
		$du = strip_tags($du);
		$au = strip_tags($au);
		$query = "SELECT count(t_log_controle.ref_fiche_controle) as nbre
				  FROM t_log_controle
				  WHERE (t_log_controle.date_controle >= :du AND t_log_controle.date_controle <= CONCAT(:au, ' 23:59:59'))
				  AND t_log_controle.ref_site_controle = :site
				  AND t_log_controle.annule = :annule" . $user_filtre;
		$stmt = $this->conn->prepare($query);
		$stmt->bindParam(":site", $site);
		$stmt->bindParam(":du", $du);
		$stmt->bindParam(":au", $au);
		$stmt->bindParam(":annule", $this->Valid);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ? $row['nbre'] : 0;
	}

	function GetAll_CompteurControled($user_context, $du, $au)
	{
		$user_filtre = $user_context->GetUserFilterControl();
		$liste_site = array();
		$query = "SELECT t_param_site_production.code_site
				  FROM t_param_site_production
				  INNER JOIN t_utilisateur_site_accessible
				  ON t_param_site_production.code_site = t_utilisateur_site_accessible.code_site
				  WHERE t_utilisateur_site_accessible.code_user = :code_user";
		$code_user = strip_tags($user_context->code_utilisateur);
		$stmt = $this->conn->prepare($query);
		$stmt->bindValue(":code_user", $code_user);
		$stmt->execute();
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$liste_site[] = $row['code_site'];
		}

		if (empty($liste_site)) {
			return 0;
		}

		$liste_site_label = implode("','", $liste_site);
		$liste_site_label = "'" . $liste_site_label . "'";

		$du = htmlspecialchars(strip_tags($du));
		$au = htmlspecialchars(strip_tags($au));
		$query = "SELECT count(t_log_controle.ref_fiche_controle) as nbre
				  FROM t_log_controle
				  WHERE (t_log_controle.date_controle >= :du AND t_log_controle.date_controle <= CONCAT(:au, ' 23:59:59'))
				  AND t_log_controle.ref_site_controle IN (" . $liste_site_label . ")
				  AND t_log_controle.annule = :annule" . $user_filtre;
		$stmt = $this->conn->prepare($query);
		$stmt->bindParam(":du", $du);
		$stmt->bindParam(":au", $au);
		$stmt->bindParam(":annule", $this->Valid);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ? $row['nbre'] : 0;
	}

	function GetAll_CVS_SYNTHE_Par_Date($user_context, $du, $au, $province_id)
	{
		$liste_site_s = array();
		$liste_site = array();
		$query = "SELECT t_param_site_production.code_site
				  FROM t_param_site_production
				  INNER JOIN t_utilisateur_site_accessible
				  ON t_param_site_production.code_site = t_utilisateur_site_accessible.code_site
				  WHERE t_utilisateur_site_accessible.code_user = :code_user";
		$code_user = strip_tags($user_context->code_utilisateur);
		$stmt = $this->conn->prepare($query);
		$stmt->bindValue(":code_user", $code_user);
		$stmt->execute();
		$nbre_total_cvs = 0;

		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$item = $this->GetSite_CVS_SYNTHE_Par_Date($user_context, $row['code_site'], $du, $au, $province_id);
			$nbre_total_cvs += count($item);
			$liste_site[] = $item;
		}

		$liste_site_s["nbre_total"] = $nbre_total_cvs;
		$liste_site_s["sites"] = $liste_site;

		return $liste_site_s;
	}

	function GetSite_CVS_SYNTHE_Par_Date($user_context, $site, $du, $au, $province_id)
	{
		$site = htmlspecialchars(strip_tags($site));
		$du = htmlspecialchars(strip_tags($du));
		$au = htmlspecialchars(strip_tags($au));
		$result = array();

		$query = "SELECT t_param_cvs.code, t_param_cvs.libelle, t_param_cvs.annule, t_param_cvs.code_province, t_param_cvs.id_site, t_param_cvs.activated
				  FROM t_param_cvs
				  WHERE t_param_cvs.id_site = :site
				  ORDER BY t_param_cvs.libelle";
		$stmt = $this->conn->prepare($query);
		$stmt->bindParam(":site", $site);
		$stmt->execute();

		while ($row_ = $stmt->fetch(PDO::FETCH_ASSOC)) {

			$item = array();
			$item["CVS"] = $row_["libelle"];

			// IDENTIFICATION - 1 seule requête simplifiée
			$user_filtre = $user_context->GetUserFilterIdentification();
			$query = "SELECT count(t_main_data.id_) as nbre
					  FROM t_main_data
					  WHERE (t_main_data.date_identification >= :du AND t_main_data.date_identification <= CONCAT(:au, ' 23:59:59'))
					  AND t_main_data.cvs_id = :id_cvs
					  AND t_main_data.annule = :annule
					  AND t_main_data.is_draft = '0'" . $user_filtre;
			$st = $this->conn->prepare($query);
			$st->bindValue(":id_cvs", $row_["code"]);
			$st->bindValue(":du", $du);
			$st->bindValue(":au", $au);
			$st->bindValue(":annule", $this->Valid);
			$st->execute();
			$ident = $st->fetch(PDO::FETCH_ASSOC);
			$item["nbre_identification"] = $ident ? $ident['nbre'] : 0;

			// INSTALLATION - 1 seule requête simplifiée
			$user_filtre = $user_context->GetUserFilterInstallation();
			$query = "SELECT count(t_log_installation.id_install) as nbre
					  FROM t_log_installation
					  INNER JOIN t_main_data ON t_log_installation.ref_identific = t_main_data.id_
					  WHERE (t_log_installation.date_fin_installation >= :du AND t_log_installation.date_fin_installation <= CONCAT(:au, ' 23:59:59'))
					  AND t_main_data.cvs_id = :id_cvs
					  AND t_log_installation.annule = :annule
					  AND t_log_installation.statut_installation = 1
					  AND t_log_installation.is_draft_install = 0
					  AND t_log_installation.approbation_installation = 1
					  AND t_log_installation.type_installation IN ('0','1')" . $user_filtre;
			$st = $this->conn->prepare($query);
			$st->bindValue(":id_cvs", $row_["code"]);
			$st->bindValue(":du", $du);
			$st->bindValue(":au", $au);
			$st->bindValue(":annule", $this->Valid);
			$st->execute();
			$ident = $st->fetch(PDO::FETCH_ASSOC);
			$item["nbre_installation"] = $ident ? $ident['nbre'] : 0;

			// CONTROLE - 1 seule requête simplifiée
			$user_filtre = $user_context->GetUserFilterControl();
			$query = "SELECT count(t_log_controle.ref_fiche_controle) as nbre
					  FROM t_log_controle
					  INNER JOIN t_main_data ON t_log_controle.ref_fiche_identification = t_main_data.id_
					  WHERE (t_log_controle.date_controle >= :du AND t_log_controle.date_controle <= CONCAT(:au, ' 23:59:59'))
					  AND t_main_data.cvs_id = :id_cvs
					  AND t_log_controle.annule = :annule" . $user_filtre;
			$st = $this->conn->prepare($query);
			$st->bindValue(":id_cvs", $row_["code"]);
			$st->bindValue(":du", $du);
			$st->bindValue(":au", $au);
			$st->bindValue(":annule", $this->Valid);
			$st->execute();
			$ident = $st->fetch(PDO::FETCH_ASSOC);
			$item["nbre_controle"] = $ident ? $ident['nbre'] : 0;

			array_push($result, $item);
		}
		return $result;
	}
}
?>
