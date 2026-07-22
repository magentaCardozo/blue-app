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
		// Added GROUP BY t_main_data.ref_site_identif
		$query = "SELECT count(t_main_data.id_) as nbre, t_main_data.ref_site_identif 
				  FROM t_main_data 
				  INNER JOIN t_param_cvs ON t_param_cvs.`code` = t_main_data.cvs_id 
				  INNER JOIN t_log_adresses ON t_main_data.adresse_id = t_log_adresses.id 
				  INNER JOIN t_param_adresse_entity AS e_quartier ON t_log_adresses.quartier_id = e_quartier.`code` 
				  INNER JOIN t_param_adresse_entity AS e_commune ON t_log_adresses.commune_id = e_commune.`code` 
				  INNER JOIN t_param_adresse_entity AS e_ville ON t_log_adresses.ville_id = e_ville.`code` 
				  INNER JOIN t_utilisateurs ON t_main_data.identificateur = t_utilisateurs.code_utilisateur 
				  INNER JOIN t_utilisateurs as t_chef_equipe ON t_main_data.chef_equipe = t_chef_equipe.code_utilisateur 
				  INNER JOIN t_param_organisme ON t_main_data.id_equipe_identification = t_param_organisme.ref_organisme 
				  INNER JOIN t_param_adresse_entity AS e_avenue ON t_log_adresses.avenue = e_avenue.`code`
				  INNER JOIN t_param_identite AS identite_client ON t_main_data.client_id = identite_client.id  
				  WHERE (DATE_FORMAT(t_main_data.date_identification,'%Y-%m-%d') BETWEEN :du AND :au) 
				  AND t_main_data.ref_site_identif = :site 
				  AND t_main_data.annule = :annule  
				  AND t_main_data.is_draft = '0'" . $user_filtre . " 
				  GROUP BY t_main_data.ref_site_identif";
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
		// Added GROUP BY t_main_data.ref_site_identif
		$query = "SELECT count(t_main_data.id_) as nbre, t_main_data.ref_site_identif 
				  FROM t_main_data 
				  INNER JOIN t_param_cvs ON t_param_cvs.`code` = t_main_data.cvs_id 
				  INNER JOIN t_log_adresses ON t_main_data.adresse_id = t_log_adresses.id 
				  INNER JOIN t_param_adresse_entity AS e_quartier ON t_log_adresses.quartier_id = e_quartier.`code` 
				  INNER JOIN t_param_adresse_entity AS e_commune ON t_log_adresses.commune_id = e_commune.`code` 
				  INNER JOIN t_param_adresse_entity AS e_ville ON t_log_adresses.ville_id = e_ville.`code` 
				  INNER JOIN t_utilisateurs ON t_main_data.identificateur = t_utilisateurs.code_utilisateur 
				  INNER JOIN t_utilisateurs as t_chef_equipe ON t_main_data.chef_equipe = t_chef_equipe.code_utilisateur 
				  INNER JOIN t_param_organisme ON t_main_data.id_equipe_identification = t_param_organisme.ref_organisme 
				  INNER JOIN t_param_adresse_entity AS e_avenue ON t_log_adresses.avenue = e_avenue.`code`
				  INNER JOIN t_param_identite AS identite_client ON t_main_data.client_id = identite_client.id  
				  WHERE (DATE_FORMAT(t_main_data.date_identification,'%Y-%m-%d') BETWEEN :du AND :au) 
				  AND t_main_data.ref_site_identif IN (" . $liste_site_label . ") 
				  AND t_main_data.annule = :annule  
				  AND t_main_data.is_draft = '0'" . $user_filtre . " 
				  GROUP BY t_main_data.ref_site_identif";
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
		// Added GROUP BY t_log_installation.ref_site_install
		$query = "SELECT count(t_log_installation.id_install) as nbre, t_log_installation.ref_site_install 
				  FROM t_log_installation 
				  INNER JOIN t_main_data ON t_log_installation.ref_identific = t_main_data.id_ 
				  INNER JOIN t_param_cvs ON t_param_cvs.`code` = t_main_data.cvs_id 
				  INNER JOIN t_log_adresses ON t_main_data.adresse_id = t_log_adresses.id 
				  INNER JOIN t_param_adresse_entity AS e_quartier ON t_log_adresses.quartier_id = e_quartier.`code` 
				  INNER JOIN t_param_adresse_entity AS e_commune ON t_log_adresses.commune_id = e_commune.`code` 
				  INNER JOIN t_param_adresse_entity AS e_ville ON t_log_adresses.ville_id = e_ville.`code` 
				  INNER JOIN t_utilisateurs ON t_main_data.identificateur = t_utilisateurs.code_utilisateur 
				  INNER JOIN t_utilisateurs as t_chef_equipe ON t_main_data.chef_equipe = t_chef_equipe.code_utilisateur 
				  INNER JOIN t_param_organisme ON t_main_data.id_equipe_identification = t_param_organisme.ref_organisme 
				  INNER JOIN t_param_adresse_entity AS e_avenue ON t_log_adresses.avenue = e_avenue.`code`
				  INNER JOIN t_param_identite AS identite_client ON t_main_data.client_id = identite_client.id
				  WHERE (DATE_FORMAT(t_log_installation.date_fin_installation,'%Y-%m-%d') BETWEEN :du AND :au) 
				  AND t_log_installation.ref_site_install = :site 
				  AND t_log_installation.annule = :annule 
				  AND t_log_installation.statut_installation = 1 
				  AND t_log_installation.is_draft_install = 0 
				  AND t_log_installation.approbation_installation = 1 
				  AND t_log_installation.type_installation = '0'" . $user_filtre . " 
				  GROUP BY t_log_installation.ref_site_install";
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
		// Added GROUP BY t_log_installation.ref_site_install
		$query = "SELECT count(t_log_installation.id_install) as nbre, t_log_installation.ref_site_install 
				  FROM t_log_installation 
				  INNER JOIN t_main_data ON t_log_installation.ref_identific = t_main_data.id_ 
				  INNER JOIN t_param_cvs ON t_param_cvs.`code` = t_main_data.cvs_id 
				  INNER JOIN t_log_adresses ON t_main_data.adresse_id = t_log_adresses.id 
				  INNER JOIN t_param_adresse_entity AS e_quartier ON t_log_adresses.quartier_id = e_quartier.`code` 
				  INNER JOIN t_param_adresse_entity AS e_commune ON t_log_adresses.commune_id = e_commune.`code` 
				  INNER JOIN t_param_adresse_entity AS e_ville ON t_log_adresses.ville_id = e_ville.`code` 
				  INNER JOIN t_utilisateurs ON t_main_data.identificateur = t_utilisateurs.code_utilisateur 
				  INNER JOIN t_utilisateurs as t_chef_equipe ON t_main_data.chef_equipe = t_chef_equipe.code_utilisateur 
				  INNER JOIN t_param_organisme ON t_main_data.id_equipe_identification = t_param_organisme.ref_organisme 
				  INNER JOIN t_param_adresse_entity AS e_avenue ON t_log_adresses.avenue = e_avenue.`code`
				  INNER JOIN t_param_identite AS identite_client ON t_main_data.client_id = identite_client.id
				  WHERE (DATE_FORMAT(t_log_installation.date_fin_installation,'%Y-%m-%d') BETWEEN :du AND :au) 
				  AND t_log_installation.ref_site_install = :site 
				  AND t_log_installation.annule = :annule 
				  AND t_log_installation.statut_installation = 1 
				  AND t_log_installation.is_draft_install = 0 
				  AND t_log_installation.approbation_installation = 1 
				  AND t_log_installation.type_installation = '1'" . $user_filtre . " 
				  GROUP BY t_log_installation.ref_site_install";
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
		// Added GROUP BY t_log_installation.ref_site_install
		$query = "SELECT count(t_log_installation.id_install) as nbre, t_log_installation.ref_site_install 
				  FROM t_log_installation
				  INNER JOIN t_main_data ON t_log_installation.ref_identific = t_main_data.id_
				  INNER JOIN t_param_cvs ON t_param_cvs.`code` = t_main_data.cvs_id 
				  INNER JOIN t_log_adresses ON t_main_data.adresse_id = t_log_adresses.id 
				  INNER JOIN t_param_adresse_entity AS e_quartier ON t_log_adresses.quartier_id = e_quartier.`code` 
				  INNER JOIN t_param_adresse_entity AS e_commune ON t_log_adresses.commune_id = e_commune.`code` 
				  INNER JOIN t_param_adresse_entity AS e_ville ON t_log_adresses.ville_id = e_ville.`code` 
				  INNER JOIN t_utilisateurs ON t_main_data.identificateur = t_utilisateurs.code_utilisateur 
				  INNER JOIN t_utilisateurs as t_chef_equipe ON t_main_data.chef_equipe = t_chef_equipe.code_utilisateur 
				  INNER JOIN t_param_organisme ON t_main_data.id_equipe_identification = t_param_organisme.ref_organisme 
				  INNER JOIN t_param_adresse_entity AS e_avenue ON t_log_adresses.avenue = e_avenue.`code`
				  INNER JOIN t_param_identite AS identite_client ON t_main_data.client_id = identite_client.id    
				  WHERE (DATE_FORMAT(t_log_installation.date_fin_installation,'%Y-%m-%d') BETWEEN :du AND :au) 
				  AND t_log_installation.ref_site_install IN (" . $liste_site_label . ") 
				  AND t_log_installation.annule = :annule 
				  AND t_log_installation.statut_installation = 1 
				  AND t_log_installation.is_draft_install = 0 
				  AND t_log_installation.approbation_installation = 1 
				  AND t_log_installation.type_installation = '0'" . $user_filtre . " 
				  GROUP BY t_log_installation.ref_site_install";
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
		// Added GROUP BY t_log_installation.ref_site_install
		$query = "SELECT count(t_log_installation.id_install) as nbre, t_log_installation.ref_site_install 
				  FROM t_log_installation
				  INNER JOIN t_main_data ON t_log_installation.ref_identific = t_main_data.id_
				  INNER JOIN t_param_cvs ON t_param_cvs.`code` = t_main_data.cvs_id 
				  INNER JOIN t_log_adresses ON t_main_data.adresse_id = t_log_adresses.id 
				  INNER JOIN t_param_adresse_entity AS e_quartier ON t_log_adresses.quartier_id = e_quartier.`code` 
				  INNER JOIN t_param_adresse_entity AS e_commune ON t_log_adresses.commune_id = e_commune.`code` 
				  INNER JOIN t_param_adresse_entity AS e_ville ON t_log_adresses.ville_id = e_ville.`code` 
				  INNER JOIN t_utilisateurs ON t_main_data.identificateur = t_utilisateurs.code_utilisateur 
				  INNER JOIN t_utilisateurs as t_chef_equipe ON t_main_data.chef_equipe = t_chef_equipe.code_utilisateur 
				  INNER JOIN t_param_organisme ON t_main_data.id_equipe_identification = t_param_organisme.ref_organisme 
				  INNER JOIN t_param_adresse_entity AS e_avenue ON t_log_adresses.avenue = e_avenue.`code`
				  INNER JOIN t_param_identite AS identite_client ON t_main_data.client_id = identite_client.id    
				  WHERE (DATE_FORMAT(t_log_installation.date_fin_installation,'%Y-%m-%d') BETWEEN :du AND :au) 
				  AND t_log_installation.ref_site_install IN (" . $liste_site_label . ") 
				  AND t_log_installation.annule = :annule 
				  AND t_log_installation.statut_installation = 1 
				  AND t_log_installation.is_draft_install = 0 
				  AND t_log_installation.approbation_installation = 1 
				  AND t_log_installation.type_installation = '1'" . $user_filtre . " 
				  GROUP BY t_log_installation.ref_site_install";
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
		// Added GROUP BY t_log_controle.ref_site_controle
		$query = "SELECT count(t_log_controle.ref_fiche_controle) as nbre, t_log_controle.ref_site_controle 
				  FROM t_log_controle  
				  INNER JOIN t_main_data ON t_log_controle.ref_fiche_identification = t_main_data.id_ 
				  INNER JOIN t_param_cvs ON t_param_cvs.`code` = t_main_data.cvs_id 
				  INNER JOIN t_log_adresses ON t_main_data.adresse_id = t_log_adresses.id 
				  INNER JOIN t_param_adresse_entity AS e_quartier ON t_log_adresses.quartier_id = e_quartier.`code` 
				  INNER JOIN t_param_adresse_entity AS e_commune ON t_log_adresses.commune_id = e_commune.`code` 
				  INNER JOIN t_param_adresse_entity AS e_ville ON t_log_adresses.ville_id = e_ville.`code` 
				  INNER JOIN t_utilisateurs ON t_main_data.identificateur = t_utilisateurs.code_utilisateur 
				  INNER JOIN t_utilisateurs as t_chef_equipe ON t_main_data.chef_equipe = t_chef_equipe.code_utilisateur 
				  INNER JOIN t_param_organisme ON t_main_data.id_equipe_identification = t_param_organisme.ref_organisme 
				  INNER JOIN t_param_adresse_entity AS e_avenue ON t_log_adresses.avenue = e_avenue.`code`
				  INNER JOIN t_param_identite AS identite_client ON t_main_data.client_id = identite_client.id
				  WHERE (DATE_FORMAT(t_log_controle.date_controle,'%Y-%m-%d') BETWEEN :du AND :au) 
				  AND t_log_controle.ref_site_controle = :site 
				  AND t_log_controle.annule = :annule" . $user_filtre . " 
				  GROUP BY t_log_controle.ref_site_controle";
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
		// Added GROUP BY t_log_controle.ref_site_controle
		$query = "SELECT count(t_log_controle.ref_fiche_controle) as nbre, t_log_controle.ref_site_controle 
				  FROM t_log_controle   
				  INNER JOIN t_main_data ON t_log_controle.ref_fiche_identification = t_main_data.id_ 
				  INNER JOIN t_param_cvs ON t_param_cvs.`code` = t_main_data.cvs_id 
				  INNER JOIN t_log_adresses ON t_main_data.adresse_id = t_log_adresses.id 
				  INNER JOIN t_param_adresse_entity AS e_quartier ON t_log_adresses.quartier_id = e_quartier.`code` 
				  INNER JOIN t_param_adresse_entity AS e_commune ON t_log_adresses.commune_id = e_commune.`code` 
				  INNER JOIN t_param_adresse_entity AS e_ville ON t_log_adresses.ville_id = e_ville.`code` 
				  INNER JOIN t_utilisateurs ON t_main_data.identificateur = t_utilisateurs.code_utilisateur 
				  INNER JOIN t_utilisateurs as t_chef_equipe ON t_main_data.chef_equipe = t_chef_equipe.code_utilisateur 
				  INNER JOIN t_param_organisme ON t_main_data.id_equipe_identification = t_param_organisme.ref_organisme 
				  INNER JOIN t_param_adresse_entity AS e_avenue ON t_log_adresses.avenue = e_avenue.`code`
				  INNER JOIN t_param_identite AS identite_client ON t_main_data.client_id = identite_client.id
				  WHERE (DATE_FORMAT(t_log_controle.date_controle,'%Y-%m-%d') BETWEEN :du AND :au) 
				  AND t_log_controle.ref_site_controle IN (" . $liste_site_label . ") 
				  AND t_log_controle.annule = :annule" . $user_filtre . " 
				  GROUP BY t_log_controle.ref_site_controle";
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

			// RECUPERATION DES COMPTEURS IDENTIFIES POUR LE CVS EN COURS
			$user_filtre = $user_context->GetUserFilterIdentification();
			// Added GROUP BY t_main_data.ref_site_identif
			$query = "SELECT count(t_main_data.id_) as nbre, t_main_data.ref_site_identif 
					  FROM t_main_data 
					  INNER JOIN t_param_cvs ON t_param_cvs.`code` = t_main_data.cvs_id 
					  INNER JOIN t_log_adresses ON t_main_data.adresse_id = t_log_adresses.id 
					  INNER JOIN t_param_adresse_entity AS e_quartier ON t_log_adresses.quartier_id = e_quartier.`code` 
					  INNER JOIN t_param_adresse_entity AS e_commune ON t_log_adresses.commune_id = e_commune.`code` 
					  INNER JOIN t_param_adresse_entity AS e_ville ON t_log_adresses.ville_id = e_ville.`code` 
					  INNER JOIN t_utilisateurs ON t_main_data.identificateur = t_utilisateurs.code_utilisateur 
					  INNER JOIN t_utilisateurs as t_chef_equipe ON t_main_data.chef_equipe = t_chef_equipe.code_utilisateur 
					  INNER JOIN t_param_organisme ON t_main_data.id_equipe_identification = t_param_organisme.ref_organisme 
					  INNER JOIN t_param_adresse_entity AS e_avenue ON t_log_adresses.avenue = e_avenue.`code`
					  INNER JOIN t_param_identite AS identite_client ON t_main_data.client_id = identite_client.id  
					  WHERE (DATE_FORMAT(t_main_data.date_identification,'%Y-%m-%d') BETWEEN :du AND :au) 
					  AND t_main_data.cvs_id = :id_cvs 
					  AND t_main_data.annule = :annule 
					  AND t_main_data.is_draft = '0'" . $user_filtre . " 
					  GROUP BY t_main_data.ref_site_identif";
			$st = $this->conn->prepare($query);
			$st->bindValue(":id_cvs", $row_["code"]);
			$st->bindValue(":du", $du);
			$st->bindValue(":au", $au);
			$st->bindValue(":annule", $this->Valid);
			$st->execute();
			$ident = $st->fetch(PDO::FETCH_ASSOC);
			$item["nbre_identification"] = $ident ? $ident['nbre'] : 0;

			// RECUPERATION DES COMPTEURS INSTALLES POUR LE CVS EN COURS
			$user_filtre = $user_context->GetUserFilterInstallation();
			// Added GROUP BY t_log_installation.ref_site_install
			$query = "SELECT count(t_log_installation.id_install) as nbre, t_log_installation.ref_site_install 
					  FROM t_log_installation
					  INNER JOIN t_main_data ON t_log_installation.ref_identific = t_main_data.id_
					  INNER JOIN t_param_cvs ON t_param_cvs.`code` = t_main_data.cvs_id 
					  INNER JOIN t_log_adresses ON t_main_data.adresse_id = t_log_adresses.id 
					  INNER JOIN t_param_adresse_entity AS e_quartier ON t_log_adresses.quartier_id = e_quartier.`code` 
					  INNER JOIN t_param_adresse_entity AS e_commune ON t_log_adresses.commune_id = e_commune.`code` 
					  INNER JOIN t_param_adresse_entity AS e_ville ON t_log_adresses.ville_id = e_ville.`code` 
					  INNER JOIN t_utilisateurs ON t_main_data.identificateur = t_utilisateurs.code_utilisateur 
					  INNER JOIN t_utilisateurs as t_chef_equipe ON t_main_data.chef_equipe = t_chef_equipe.code_utilisateur 
					  INNER JOIN t_param_organisme ON t_main_data.id_equipe_identification = t_param_organisme.ref_organisme 
					  INNER JOIN t_param_adresse_entity AS e_avenue ON t_log_adresses.avenue = e_avenue.`code`
					  INNER JOIN t_param_identite AS identite_client ON t_main_data.client_id = identite_client.id    
					  WHERE (DATE_FORMAT(t_log_installation.date_fin_installation,'%Y-%m-%d') BETWEEN :du AND :au) 
					  AND t_main_data.cvs_id = :id_cvs 
					  AND t_log_installation.annule = :annule 
					  AND t_log_installation.statut_installation = 1 
					  AND t_log_installation.is_draft_install = 0 
					  AND t_log_installation.approbation_installation = 1 
					  AND t_log_installation.type_installation IN ('0','1')" . $user_filtre . " 
					  GROUP BY t_log_installation.ref_site_install";
			$st = $this->conn->prepare($query);
			$st->bindValue(":id_cvs", $row_["code"]);
			$st->bindValue(":du", $du);
			$st->bindValue(":au", $au);
			$st->bindValue(":annule", $this->Valid);
			$st->execute();
			$ident = $st->fetch(PDO::FETCH_ASSOC);
			$item["nbre_installation"] = $ident ? $ident['nbre'] : 0;

			// RECUPERATION DES COMPTEURS CONTROLES POUR LE CVS EN COURS
			$user_filtre = $user_context->GetUserFilterControl();
			// Added GROUP BY t_log_controle.ref_site_controle
			$query = "SELECT count(t_log_controle.ref_fiche_controle) as nbre, t_log_controle.ref_site_controle 
					  FROM t_log_controle  
					  INNER JOIN t_main_data ON t_log_controle.ref_fiche_identification = t_main_data.id_  
					  INNER JOIN t_param_cvs ON t_param_cvs.`code` = t_main_data.cvs_id 
					  INNER JOIN t_log_adresses ON t_main_data.adresse_id = t_log_adresses.id 
					  INNER JOIN t_param_adresse_entity AS e_quartier ON t_log_adresses.quartier_id = e_quartier.`code` 
					  INNER JOIN t_param_adresse_entity AS e_commune ON t_log_adresses.commune_id = e_commune.`code` 
					  INNER JOIN t_param_adresse_entity AS e_ville ON t_log_adresses.ville_id = e_ville.`code` 
					  INNER JOIN t_utilisateurs ON t_main_data.identificateur = t_utilisateurs.code_utilisateur 
					  INNER JOIN t_utilisateurs as t_chef_equipe ON t_main_data.chef_equipe = t_chef_equipe.code_utilisateur 
					  INNER JOIN t_param_organisme ON t_main_data.id_equipe_identification = t_param_organisme.ref_organisme 
					  INNER JOIN t_param_adresse_entity AS e_avenue ON t_log_adresses.avenue = e_avenue.`code`
					  INNER JOIN t_param_identite AS identite_client ON t_main_data.client_id = identite_client.id 
					  WHERE (DATE_FORMAT(t_log_controle.date_controle,'%Y-%m-%d') BETWEEN :du AND :au) 
					  AND t_main_data.cvs_id = :id_cvs 
					  AND t_log_controle.annule = :annule" . $user_filtre . " 
					  GROUP BY t_log_controle.ref_site_controle";
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