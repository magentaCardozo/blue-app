<?php
// session_start();
$mnu_title = "";
$page_title = "Accueil";
$active = "dashboard";
$parambase = "";

require_once './vendor/autoload.php';
require_once 'vendor/autoload.php';
require_once 'loader/init.php';
//loading Classes filess
Autoloader::Load('classes');


/*
include_once 'include/database_pdo.php';
include_once 'classes/class.utilisateur.php'; 
include_once 'classes/class.province.php'; 
include_once 'classes/class.dashboard.php'; 
include_once 'classes/class.site.php'; 
include_once 'classes/class.utils.php'; */
include_once 'core.php';
header('Content-type: text/html;charset=utf-8');

// $database = new Database();
// $db = $database->getConnection();
$utilisateur = new Utilisateur($db);
$province_class = new Province($db);
$site_classe = new Site($db);
$dashview = new Dashviewer($db);




$cacher = new Cacher();
$cacher->setPrefix("dashboard");
//$utilisateur->code_utilisateur=$_SESSION['uSession'];

if ($utilisateur->is_logged_in() == false) {
	$utilisateur->redirect('login.php');
}
$utilisateur->readOne();

//test tree
//echo ($utilisateur->GetUserFilterInstallation());//and code_installateur in ('007','005','008','009')
//echo ($utilisateur->GetUserFilterIdentification());//and identificateur in ('007','005','008','009')
//echo ($utilisateur->GetUserFilterControl());//and controleur in ('007','005','008','009')


$province = isset($_POST['province']) ? $_POST['province'] : '';
$site = isset($_POST['site']) ? $_POST['site'] : NULL;
$du = isset($_POST['Du']) ? Utils::ClientToDbDateFormat($_POST['Du']) : Utils::GetFirstDayOfCurrentMonth();
$au = isset($_POST['Au']) ? Utils::ClientToDbDateFormat($_POST['Au']) : Utils::GetLastDayOfCurrentMonth();
$du_ = isset($_POST['Du']) ? ($_POST['Du']) : Utils::DbToClientDateFormat(Utils::GetFirstDayOfCurrentMonth());
$au_ = isset($_POST['Au']) ? ($_POST['Au']) : Utils::DbToClientDateFormat(Utils::GetLastDayOfCurrentMonth());
$message = "Accueil";
$first_site = "";
if (isset($_POST['site']) && isset($_POST['Du']) && isset($_POST['Au'])) {
	$site_classe->code_site = $site;
	$site_classe->GetDetailIN();
	$message = "Production " . $site_classe->intitule_site . " DU " . $_POST['Du'] . " AU " . $_POST['Au'];
}


?>


<!doctype html>
<html lang="fr">

<head>
	<style>
		.ticket-replaced {
			background-color: #d359ff;
		}

		/** SPINNER CREATION **/

		.loader {
			position: relative;
			text-align: center;
			margin: 15px auto 35px auto;
			z-index: 9999;
			display: block;
			width: 80px;
			height: 80px;
			border: 10px solid rgba(0, 0, 0, .3);
			border-radius: 50%;
			border-top-color: #5969ff;
			animation: spin 1s ease-in-out infinite;
			-webkit-animation: spin 1s ease-in-out infinite;
		}

		@keyframes spin {
			to {
				-webkit-transform: rotate(360deg);
			}
		}

		@-webkit-keyframes spin {
			to {
				-webkit-transform: rotate(360deg);
			}
		}


		/** MODAL STYLING **/

		.modal-content {
			border-radius: 0px;
			box-shadow: 0 0 20px 8px rgba(0, 0, 0, 0.7);
		}

		.modal-backdrop.show {
			opacity: 0.75;
		}

		.loader-txt {
			p {
				font-size: 13px;
				color: #666;

				small {
					font-size: 11.5px;
					color: #999;
				}
			}
		}

		/*dashboard-spinner {
    margin: 0px 8px;
    border-radius: 50%;
    background-color: transparent;
    border: 6px solid transparent;
    border-top: 6px solid #5969ff;
    border-left: 6px solid #5969ff;
    -webkit-animation: 1s spin linear infinite;
    animation: 1s spin linear infinite;
    display: inline-block;
}

.spinner-xl {
    width: 120px;
    height: 120px;
}*/
	</style>
	<?php
	include_once "layout_style.php";
	?>

	<style>
		.navbar {
			background: transparent !important;
			box-shadow: none !important;
			transition: .3s;
		}

		.navbar.bg-white.white {
			background: #fff !important;
		}

		

		.navbar .nav-item.dropdown span {
			color: #fff !important;
		}

		.navbar.bg-white.white .nav-item.dropdown span {
			color: var(--colorTitle) !important;
		}

		.navbar.bg-white.white .item-site {
			color: #38d594 !important;
			background: #f2fff2 !important;
			border: 1px solid #38d594 !important;
		}

		.item-site {
			border-color: transparent;
			color: #fff;
			background: #ffffff30;
		}

		.banner-linear {
			padding-top: 70px;
			padding-bottom: 90px;
			margin-top: -104px;
		}

		/* Styles pour les cartes CVS */
		.cvs-card {
			cursor: pointer;
			transition: all 0.3s ease;
			border: 1px solid #e9ecef;
			border-radius: 10px;
			overflow: hidden;
		}

		.cvs-card:hover {
			box-shadow: 0 5px 15px rgba(0,0,0,0.1);
			transform: translateY(-2px);
		}

		.cvs-card .card-header {
			background: #f8f9fa;
			padding: 15px 20px;
			display: flex;
			justify-content: space-between;
			align-items: center;
			border-bottom: 1px solid #e9ecef;
		}

		.cvs-card .card-header h5 {
			margin: 0;
			font-weight: 600;
			color: #2d3748;
		}

		.cvs-card .btn-toggle {
			background: transparent;
			color: #94a3b8;
			border: none;
			padding: 5px 8px;
			display: flex;
			align-items: center;
			justify-content: center;
			transition: all 0.3s ease;
			font-size: 1rem;
			cursor: pointer;
		}

		.cvs-card .btn-toggle:hover {
			color: #5969ff;
		}

		.cvs-card .btn-toggle.expanded {
			transform: rotate(180deg);
		}

		.cvs-card .btn-toggle i {
			font-size: 1.1rem;
			transition: transform 0.3s ease;
		}

		.cvs-card .card-body {
			padding: 0;
			max-height: 0;
			overflow: hidden;
			transition: max-height 0.3s ease, padding 0.3s ease;
		}

		.cvs-card .card-body.show {
			max-height: 500px;
			padding: 20px;
		}

		.cvs-card .detail-item {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 10px 0;
			border-bottom: 1px solid #f1f3f5;
		}

		.cvs-card .detail-item:last-child {
			border-bottom: none;
		}

		.cvs-card .detail-item .label {
			color: #6c757d;
			font-weight: 500;
		}

		.cvs-card .detail-item .value {
			font-weight: 600;
			font-size: 1.1rem;
		}

		.cvs-identif-text { color: #dc3545; }
		.cvs-install-text { color: #2ec551; }
		.cvs-control-text { color: #5969ff; }
		.cvs-replace-text { color: #d359ff; }

		.text-card {
			color: #2d3748;
		}

		/* Styles pour la recherche */
		.search-wrapper {
			position: relative;
			max-width: 400px;
		}

		.search-wrapper .form-control {
			padding-right: 45px;
			border-radius: 30px;
			border: 1px solid #e2e8f0;
			background: #f8fafc;
			transition: all 0.3s ease;
		}

		.search-wrapper .form-control:focus {
			background: #ffffff;
			border-color: #cbd5e1;
			box-shadow: 0 0 0 3px rgba(89, 105, 255, 0.1);
		}

		.search-wrapper .form-control::placeholder {
			color: #a0aec0;
			font-size: 0.9rem;
		}

		.btn-filter {
			position: absolute;
			right: 8px;
			top: 50%;
			transform: translateY(-50%);
			background: transparent;
			border: none;
			color: #94a3b8;
			padding: 5px 10px;
			font-size: 0.9rem;
			transition: all 0.3s ease;
			cursor: pointer;
		}

		.btn-filter:hover {
			color: #5969ff;
		}

		.btn-filter i {
			font-size: 1rem;
		}

		.btn-filter .badge-filter {
			position: absolute;
			top: -5px;
			right: -5px;
			background: #5969ff;
			color: white;
			border-radius: 50%;
			width: 18px;
			height: 18px;
			font-size: 0.6rem;
			display: none;
			align-items: center;
			justify-content: center;
		}

		.btn-filter .badge-filter.active {
			display: flex;
		}

		.cvs-item.hidden {
			display: none !important;
		}

		.empty-state {
			text-align: center;
			padding: 40px 20px;
			color: #94a3b8;
		}

		.empty-state i {
			font-size: 3rem;
			margin-bottom: 15px;
			display: block;
			color: #cbd5e1;
		}

		.filter-count {
			color: #94a3b8;
			font-size: 0.9rem;
		}
	</style>
</head>

<body>

	<!-- main wrapper -->
	<!-- ============================================================== -->
	<div class="dashboard-main-wrapper">
		<!-- ============================================================== -->
		<!-- navbar -->
		<!-- ============================================================== -->
		<?php
		include_once "layout_top_bar.php";
		include_once "layout_side_bar.php";
		?>
		<!-- ============================================================== -->
		<!-- wrapper  -->
		<!-- ============================================================== -->
		<div class="dashboard-wrapper" id="dashboard">
			<div class="dashboard-ecommerce">
				<div class="banner-linear">
					<div class="container-fluid px-lg-4">
						<div class="d-flex justify-content-between align-items-end">
							<div class="text-star">
								<!-- <h6><?php echo $message; ?></h6> -->
								<h1>Bienvenu sur BlueApp</h1>
								<p>Votre système de monitoring intelligent</p>
							</div>
						</div>
					</div>
				</div>
				<div class="container-fluid dashboard-content px-lg-4 pt-0">
					<!-- ============================================================== -->
					<!-- pageheader  -->
					<!-- ============================================================== -->
					<!-- <div class="row">
						<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
							<div class="page-header">
								<h2 class="pageheader-title"><?php echo $mnu_title; ?></h2>
								<div class="page-breadcrumb">
									<nav aria-label="breadcrumb">
										<ol class="breadcrumb">
											<li class="breadcrumb-item"><a href="#" class="breadcrumb-link"></a></li>
										</ol>
									</nav>
								</div>
							</div>
						</div>
					</div> -->
					<!-- ============================================================== -->
					<!-- end pageheader  -->
					<!-- ============================================================== -->
					<?php if ($utilisateur->HasDroits("12_20")) { ?>
						<div class="ecommerce-widget">

							<div class="row">
								<!-- ============================================================== -->
								<!-- validation form -->
								<!-- ============================================================== -->
								<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">

									<div class="card block-search-site">

										<div class="card-body">
											<form class="needs-validation mb-0" method="post" action="accueil.php">
												<div class="form-row align-items-center">

													<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 col-12 mb-2">
														<label for="validationCustom04">Site</label>
														<div class="form-group">
															<select class="form-control" id="site" name="site" required>
																<!-- 				<option value='ALL'>Toutes</option>  -->
																<?php
																$multi_access = false;
																if ($utilisateur->HasDroits("10_470")) {
																	$multi_access = true;
																}
																//$site_array=$site_classe->GetAllSiteAccessibleForUser($utilisateur->code_utilisateur,$multi_access); 
																// dd(['get-all-site-accessible-for-user', $utilisateur->code_utilisateur]);
																$cacheKey = ['get-all-site-accessible-for-user', $utilisateur->code_utilisateur];
																$site_array = $cacher->get($cacheKey, function ()  use ($site_classe, $utilisateur) {
																	$stmt =  $site_classe->GetAllSiteAccessibleForUser($utilisateur);
																	return $stmt->fetchAll(PDO::FETCH_ASSOC);
																});
																// dump($site_array);
																// $site_array =  $stmt;

																$deja = false;
																// $nbre_site_ = $site_array->rowCount();
																$nbre_site_ = count($site_array);
																// if(
																//$site =USER_SITE_ID;
																$first_site  = $USER_SITE_ID;
																if ($multi_access == false) {
																	echo "<option selected='selected' value='{$USER_SITE_ID}'>{$USER_SITENAME}</option>";
																}
																foreach ($site_array as $row_) {
																	//$options.= "<option value='{$row_["code_site"]}'>{$row_["intitule_site"]}</option>";
																	if ($deja == false) {
																		$deja = true;
																		if ($multi_access == true) {
																			$first_site = $MULTI_ACCESS_SITE_CODE;
																			if ($site == $first_site) {
																				echo "<option selected='selected' value='{$MULTI_ACCESS_SITE_CODE}'>{$MULTI_ACCESS_SITE_LABEL}</option>";
																			} else {
																				echo "<option value='{$MULTI_ACCESS_SITE_CODE}'>{$MULTI_ACCESS_SITE_LABEL}</option>";
																			}
																		} else {
																			$first_site = $row_["code_site"];
																		}
																	}

																	if ($site == $row_["code_site"]) {
																		echo "<option selected='selected' value='{$row_["code_site"]}'>{$row_["intitule_site"]}</option>";
																	} else {
																		echo "<option value='{$row_["code_site"]}'>{$row_["intitule_site"]}</option>";
																	}
																}
																if ($nbre_site_ == 0) {
																	if ($multi_access == true) {
																		$first_site = $MULTI_ACCESS_SITE_CODE;
																		// if($site == $first_site){
																		if ($site == $first_site || $site == NULL) {
																			echo "<option selected='selected' value='{$MULTI_ACCESS_SITE_CODE}'>{$MULTI_ACCESS_SITE_LABEL}</option>";
																		} else {
																			echo "<option value='{$MULTI_ACCESS_SITE_CODE}'>{$MULTI_ACCESS_SITE_LABEL}</option>";
																		}
																	}
																}
																//$options.= "<option value='{$row_["code_site"]}'>{$row_["intitule_site"]}</option>";



																if ($site == NULL) {
																	$site = $first_site;
																	$site_classe->code_site = $first_site;
																	$site_classe->GetDetailIN();
																	//$message="Production ".$site_classe->intitule_site." DU ".$du_." AU ".$au_;
																}
																?></select>
														</div>
													</div>
													<div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 col-12 mb-2 ">
														<label for="validationCustom05">Du</label>
														<div class="form-group">
															<div class="input-group date">
																<input type="text" class="form-control datetimepicker-input" name="Du" id="Du" required value="<?php echo $du_; ?>" />
																<div class="input-group-append">
																	<div class="input-group-text" id="add_on_du"><i class="far fa-calendar-alt"></i></div>
																</div>
															</div>
														</div>
													</div>
													<div class="col-xl-3 col-lg-3 col-md-12 col-sm-12 col-12 mb-2 ">
														<label for="validationCustom05">Au</label>
														<div class="form-group">
															<div class="input-group date">
																<input type="text" class="form-control datetimepicker-input" name="Au" id="Au" required value="<?php echo $au_; ?>" />
																<div class="input-group-append">
																	<div class="input-group-text" id="add_on_au"><i class="far fa-calendar-alt"></i></div>
																</div>
															</div>
														</div>
													</div>
													<div class="col-xl-1 col-lg-2 col-md-12 col-sm-12 col-12 ">
														<p class="text-right">
															<button type="submit" class="btn btn-primary w-100">
																<i class="fas fa-sync text-white"></i></button>
														</p>
													</div>
												</div>
											</form>
										</div>
									</div>
									<!-- ============================================================== -->
									<!-- end validation form -->
									<!-- ============================================================== -->
								</div>
							</div>
							<div class="row">
								<div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 col-12">
									<div class="card card-1 card-widget">
										<div class="justify-between d-flex align-items-center">
											<div style="width: 70%" class="me-2">
												<h4>Identifications</h4>
											</div>
											<div style="width: 30%" class="d-flex justify-content-end">
												<div class="icon" style="background: #dc3545">
													<i class="fas fa-folder-open"></i>
												</div>
											</div>
										</div>
										<h5>
											<?php


											if ($site == $MULTI_ACCESS_SITE_CODE) {
												echo $cacher->get(['get-all-compteur-identified', $utilisateur->code_utilisateur, $du, $au], function () use ($dashview, $utilisateur, $du, $au) {
													return $dashview->GetAll_CompteurIdentified($utilisateur, $du, $au);
												});
											} else {
												echo $cacher->get(['get-site-compteur-identified', $utilisateur->code_utilisateur, $du, $au], function () use ($dashview, $utilisateur, $du, $au, $site) {
													return $dashview->GetSite_CompteurIdentified($utilisateur, $site, $du, $au);
												});
											}

											?>
										</h5>
									</div>
								</div>

								<div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 col-12">
									<div class="card card-1 card-widget">
										<div class="justify-between d-flex align-items-center">
											<div style="width: 70%" class="me-2">
												<h4>Installations</h4>
											</div>
											<div style="width: 30%" class="d-flex justify-content-end">
												<div class="icon" style="background: #2ec551">
													<i class="fas fa-handshake"></i>
												</div>
											</div>
										</div>
										<h5>
											<?php

											if ($site == $MULTI_ACCESS_SITE_CODE) {
												echo $cacher->get(['get-all-compteur-installed', $utilisateur->code_utilisateur, $du, $au], function () use ($dashview, $utilisateur, $du, $au, $site) {
													return $dashview->GetAll_CompteurInstalled($utilisateur, $du, $au);
												});
											} else {
												echo $cacher->get(['get-site-compteur-installed', $utilisateur->code_utilisateur, $du, $au], function () use ($dashview, $utilisateur, $du, $au, $site) {
													return  $dashview->GetSite_CompteurInstalled($utilisateur, $site, $du, $au);
												});
											}
											?>
										</h5>
									</div>
								</div>
								<div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 col-12">
									<div class="card card-1 card-widget">
										<div class="justify-between d-flex align-items-center">
											<div style="width: 70%" class="me-2">
												<h4>Controles</h4>
											</div>
											<div style="width: 30%" class="d-flex justify-content-end">
												<div class="icon" style="background: #5969ff">
													<i class="fas fa-edit"></i>
												</div>
											</div>
										</div>
										<h5>
											<?php



											if ($site == $MULTI_ACCESS_SITE_CODE) {
												echo $cacher->get(['get-all-compteur-controlled', $utilisateur->code_utilisateur, $du, $au], function () use ($dashview, $utilisateur, $du, $au, $site) {
													return  $dashview->GetAll_CompteurControled($utilisateur, $du, $au);
												});
											} else {
												echo $cacher->get(['get-site-compteur-controlled', $utilisateur->code_utilisateur, $du, $au], function () use ($dashview, $utilisateur, $du, $au, $site) {
													return $dashview->GetSite_CompteurControled($utilisateur, $site, $du, $au);
												});
											}
											?>
										</h5>
									</div>
								</div>
								<div class="col-xl-3 col-lg-6 col-md-6 col-sm-12 col-12">
									<div class="card card-1 card-widget">
										<div class="justify-between d-flex align-items-center">
											<div style="width: 70%" class="me-2">
												<h4>Remplacements</h4>
											</div>
											<div style="width: 30%" class="d-flex justify-content-end">
												<div class="icon" style="background: #d359ff">
													<i class="fas fa-cogs"></i>
												</div>
											</div>
										</div>
										<h5>
											<?php



											if ($site == $MULTI_ACCESS_SITE_CODE) {
												echo $cacher->get(['get-all-compteur-replaced', $utilisateur->code_utilisateur, $du, $au], function () use ($dashview, $utilisateur, $du, $au, $site) {
													return $dashview->GetAll_CompteurReplaced($utilisateur, $du, $au);
												});
											} else {
												echo $cacher->get(['get-site-compteur-replaced', $utilisateur->code_utilisateur, $du, $au], function () use ($dashview, $utilisateur, $du, $au, $site) {
													return $dashview->GetSite_CompteurReplaced($utilisateur, $site, $du, $au);
												});
											}
											?>
										</h5>
									</div>
								</div>


							</div>
							<?php

							if ($site == $MULTI_ACCESS_SITE_CODE) {
								$synt =  $cacher->get(['get-all-cvs-synthe-par-date', $utilisateur->code_utilisateur, $du, $au, $USER_SITE_PROVINCE], function () use ($dashview, $utilisateur, $du, $au, $site, $USER_SITE_PROVINCE) {
									return $dashview->GetAll_CVS_SYNTHE_Par_Date($utilisateur, $du, $au, $USER_SITE_PROVINCE);
								});

								$nbre_synthese = $synt["nbre_total"];
								$synthese = $synt["sites"];
							} else {
								$synthese =  $cacher->get(['get-site-cvs-synthe-par-date', $utilisateur->code_utilisateur, $du, $au, $USER_SITE_PROVINCE], function () use ($dashview, $utilisateur, $du, $au, $site, $USER_SITE_PROVINCE) {
									return $dashview->GetSite_CVS_SYNTHE_Par_Date($utilisateur, $site, $du, $au, $USER_SITE_PROVINCE);
								});

								$nbre_synthese = count($synthese);
							}
							?>
							<div class="row">
								<!-- ============================================================== -->

								<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
									<div class="card card-table-lg">
										<div class="card-header d-flex justify-content-between align-items-center flex-wrap">
											<h3 class="section-title mb-0 d-flex align-items-center">
												Synthèse par CVS
												<span class="badge badge-secondary ml-3" id="cvsTotalCount"><?php echo $nbre_synthese; ?></span>
												<span class="filter-count ml-3" id="cvsFilterCount" style="display:none;">
													<span id="cvsVisibleCount"><?php echo $nbre_synthese; ?></span> affiché(s)
												</span>
											</h3>
											<div class="search-wrapper mt-2 mt-sm-0">
												<input type="text" class="form-control" id="cvsSearchInput" placeholder="Rechercher un CVS...">
												<button class="btn-filter" id="cvsFilterBtn" title="Filtrer les CVS">
													<i class="fas fa-search"></i>
													<span class="badge-filter" id="filterBadge">0</span>
												</button>
											</div>
										</div>
										<div class="card-body">
											<div class="row" id="cvsContainer">
												<?php

												if ($site == $MULTI_ACCESS_SITE_CODE) {
													foreach ($synthese as $item_site) {
														foreach ($item_site as $item) {
												?>
															<div class="col-xl-4 col-lg-6 col-md-6 col-sm-12 col-12 mb-3 cvs-item" data-cvs-name="<?php echo strtolower($item["CVS"]); ?>">
																<div class="cvs-card card">
																	<div class="card-header">
																		<h5><?php echo $item["CVS"]; ?></h5>
																		<button class="btn-toggle" onclick="toggleCVS(this)">
																			<i class="fas fa-chevron-down"></i>
																		</button>
																	</div>
																	<div class="card-body">
																		<div class="detail-item">
																			<span class="label"><i class="fas fa-folder-open cvs-identif-text"></i> Identification</span>
																			<span class="value cvs-identif-text"><?php echo $item["nbre_identification"]; ?></span>
																		</div>
																		<div class="detail-item">
																			<span class="label"><i class="fas fa-handshake cvs-install-text"></i> Installation</span>
																			<span class="value cvs-install-text"><?php echo $item["nbre_installation"]; ?></span>
																		</div>
																		<div class="detail-item">
																			<span class="label"><i class="fas fa-edit cvs-control-text"></i> Contrôle</span>
																			<span class="value cvs-control-text"><?php echo $item["nbre_controle"]; ?></span>
																		</div>
																	</div>
																</div>
															</div>
														<?php

														}
													}
												} else {
													foreach ($synthese as $item) {
														?>
														<div class="col-xl-4 col-lg-6 col-md-6 col-sm-12 col-12 mb-3 cvs-item" data-cvs-name="<?php echo strtolower($item["CVS"]); ?>">
															<div class="cvs-card card">
																<div class="card-header">
																	<h5><?php echo $item["CVS"]; ?></h5>
																	<button class="btn-toggle" onclick="toggleCVS(this)">
																		<i class="fas fa-chevron-down"></i>
																	</button>
																</div>
																<div class="card-body">
																	<div class="detail-item">
																		<span class="label"><i class="fas fa-folder-open cvs-identif-text"></i> Identification</span>
																		<span class="value cvs-identif-text"><?php echo $item["nbre_identification"]; ?></span>
																	</div>
																	<div class="detail-item">
																		<span class="label"><i class="fas fa-handshake cvs-install-text"></i> Installation</span>
																		<span class="value cvs-install-text"><?php echo $item["nbre_installation"]; ?></span>
																	</div>
																	<div class="detail-item">
																		<span class="label"><i class="fas fa-edit cvs-control-text"></i> Contrôle</span>
																		<span class="value cvs-control-text"><?php echo $item["nbre_controle"]; ?></span>
																	</div>
																</div>
															</div>
														</div>
												<?php

													}
												}

												?>
											</div>
											<div class="empty-state" id="emptyState" style="display:none;">
												<i class="fas fa-search"></i>
												<p>Aucun CVS trouvé pour votre recherche</p>
											</div>
										</div>
									</div>

								</div>


								<!-- end recent orders  -->
							</div>
						</div>
					<?php				} ?>
				</div>
			</div>
			<!-- ============================================================== -->
			<!-- footer -->
			<!-- ============================================================== -->
			<?php
			//include_once "layout_footer.php";
			?>
		</div>
		<!-- ============================================================== -->
		<!-- end wrapper  -->
		<!-- ============================================================== -->
	</div>
	<!-- ============================================================== -->
	<!-- end main wrapper  -->
	<!-- ============================================================== -->
	<!-- Optional JavaScript -->
	<!-- Modal -->
	<div class="modal" id="loadMe" tabindex="-1" role="dialog" aria-hidden="true" aria-labelledby="getCroppedCanvasTitle">
		<div class="modal-dialog modal-sm" role="document">
			<div class="modal-content">

				<div class="modal-body text-center">
					<!-- <span class="dashboard-spinner spinner-primary spinner-xxl"></span> -->
					<div class="loader"></div>
					<div class="loader-txt">
						<p id="loading_msg"></p>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
	include_once "layout_script.php";
	?>
	<script>
		jQuery(document).ready(function($) {
			'use strict';

			$("#add_on_du").click(function() {
				$('#Du').datetimepicker('show');
			});
			$("#add_on_au").click(function() {
				$('#Au').datetimepicker('show');
			});

			if ($("#Du").length) {
				$('#Du').datetimepicker({
					format: 'dd/mm/yyyy',
					language: 'fr',
					weekStart: 1,
					todayBtn: 1,
					autoclose: 1,
					minView: 2
				});

			}
			if ($("#Au").length) {
				$('#Au').datetimepicker({
					format: 'dd/mm/yyyy',
					language: 'fr',
					weekStart: 1,
					todayBtn: 1,
					autoclose: 1,
					minView: 2
				});

			}

			// Fonction pour gérer le toggle des CVS
			window.toggleCVS = function(btn) {
				const card = btn.closest('.cvs-card');
				const body = card.querySelector('.card-body');
				const icon = btn.querySelector('i');
				
				if (body.classList.contains('show')) {
					body.classList.remove('show');
					btn.classList.remove('expanded');
					icon.className = 'fas fa-chevron-down';
				} else {
					body.classList.add('show');
					btn.classList.add('expanded');
					icon.className = 'fas fa-chevron-up';
				}
			};

			// Fonction de filtrage des CVS
			function filterCVS() {
				const searchTerm = $('#cvsSearchInput').val().toLowerCase().trim();
				const items = $('.cvs-item');
				let visibleCount = 0;
				let hiddenCount = 0;

				items.each(function() {
					const cvsName = $(this).data('cvs-name');
					if (searchTerm === '' || cvsName.indexOf(searchTerm) !== -1) {
						$(this).removeClass('hidden').show();
						visibleCount++;
					} else {
						$(this).addClass('hidden').hide();
						hiddenCount++;
					}
				});

				// Mettre à jour les compteurs
				$('#cvsVisibleCount').text(visibleCount);
				
				if (searchTerm !== '') {
					$('#cvsFilterCount').show();
					if (hiddenCount > 0) {
						$('#filterBadge').text(hiddenCount).addClass('active');
					} else {
						$('#filterBadge').removeClass('active');
					}
				} else {
					$('#cvsFilterCount').hide();
					$('#filterBadge').removeClass('active');
				}

				// Afficher/masquer l'état vide
				if (visibleCount === 0 && searchTerm !== '') {
					$('#emptyState').show();
				} else {
					$('#emptyState').hide();
				}
			}

			// Événement de recherche avec debounce
			let searchTimeout;
			$('#cvsSearchInput').on('input', function() {
				clearTimeout(searchTimeout);
				searchTimeout = setTimeout(filterCVS, 150);
			});

			// Bouton de filtre
			$('#cvsFilterBtn').on('click', function(e) {
				e.preventDefault();
				if ($('#cvsSearchInput').val() !== '') {
					$('#cvsSearchInput').val('');
					filterCVS();
					$('#cvsSearchInput').focus();
				} else {
					$('#cvsSearchInput').focus();
				}
			});

			// Touche Escape
			$('#cvsSearchInput').on('keyup', function(e) {
				if (e.key === 'Escape') {
					$(this).val('');
					filterCVS();
				}
			});

			// Initialiser le filtre au chargement
			filterCVS();

		});
		$(window).scroll(function() {

			if ($(this).scrollTop() > 40) {
				$(".navbar").addClass('white');

			} else {
				$(".navbar").removeClass('white');
			}

		});
	</script>
	<!--
        $(document).ready(function () {
            $('#sidebarCollapse').on('click', function () {
                $('#sidebar').toggleClass('sidebar-collapse');
                $('#dashboard').toggleClass('dashboard-collapse');
            });
        });
   -->
</body>

<?php

$db = null;
?>

</html>