<?php
// session_start();
$mnu_title = "";
$page_title = "Rapport installation";
$active = "rpt_install";
$parambase = "";

require_once 'vendor/autoload.php';
require_once 'loader/init.php';
Autoloader::Load('classes');
include_once 'core.php';

$database = new Database();
$db = $database->getConnection();
$utilisateur = new Utilisateur($db);
$province_class = new Province($db);
$site_classe = new Site($db);
$cvs_class = new CVS($db);

$dashview = new Dashviewer($db);
if ($utilisateur->is_logged_in() == false) {
	$utilisateur->redirect('login.php');
}
$utilisateur->readOne();

// Traitement de la requête AJAX pour récupérer les CVS par site
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'get_cvs_by_site') {
	header('Content-Type: application/json');
	
	$site_codes = isset($_POST['site_codes']) ? $_POST['site_codes'] : '';
	$response = array();
	
	if ($site_codes == 'ALL') {
		// Récupérer tous les CVS
		$cvs_list = $cvs_class->read();
		while ($cvs_row = $cvs_list->fetch(PDO::FETCH_ASSOC)) {
			$response[] = array(
				'code' => $cvs_row['code'],
				'libelle' => $cvs_row['libelle']
			);
		}
	} else {
		// Récupérer les CVS pour les sites spécifiques
		$site_array = explode(',', $site_codes);
		$all_cvs = array();
		
		foreach ($site_array as $site_code) {
			$site_code = trim($site_code);
			if (!empty($site_code)) {
				$cvs_list = $cvs_class->GetSiteCVS($site_code);
				while ($cvs_row = $cvs_list->fetch(PDO::FETCH_ASSOC)) {
					// Éviter les doublons
					if (!isset($all_cvs[$cvs_row['code']])) {
						$all_cvs[$cvs_row['code']] = array(
							'code' => $cvs_row['code'],
							'libelle' => $cvs_row['libelle']
						);
					}
				}
			}
		}
		
		$response = array_values($all_cvs);
	}
	
	echo json_encode($response);
	exit();
}
?>

<!doctype html>
<html lang="fr">

<head>
	<style>
		.select2-container--default .select2-selection--multiple .select2-selection__choice {
			background-color: #5969ff;
			border-color: #4a5adf;
			color: white;
		}
		
		.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
			color: white;
		}
		
		.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
			color: #ffcccc;
		}
	</style>
	<link href="assets/css/select2.css" rel="stylesheet">

	<?php
	include_once "layout_style.php";
	?>

</head>

<body>

	<!-- main wrapper -->
	<div class="dashboard-main-wrapper">
		<?php
		include_once "layout_top_bar.php";
		include_once "layout_side_bar.php";
		?>
		<!-- ============================================================== -->
		<!-- wrapper  -->
		<!-- ============================================================== -->
		<div class="dashboard-wrapper" id="dashboard">
			<div class="dashboard-ecommerce">
				<div class="container-fluid dashboard-content ">
					<!-- ============================================================== -->
					<!-- pageheader  -->
					<!-- ============================================================== -->
					<div class="row">
						<div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 col-12">
							<div class="page-header">
								<h2 class="pageheader-title"><?php echo $mnu_title; ?></h2>
								<h2>Rapport des compteurs installés</h2>
								<div class="page-breadcrumb">
									<nav aria-label="breadcrumb">
										<ol class="breadcrumb">
											<li class="breadcrumb-item"><a href="#" class="breadcrumb-link"></a></li>
										</ol>
									</nav>
								</div>
							</div>
						</div>
					</div>
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

									<div class="card">

										<div class="card-body">
											<form method="post" action="reporting/rpt_compteur_install_xls.php" target="blank">
												<div class="row">

													<div class="form-row">

														<!-- 1. Champ SITE -->
														<div class="col-xl-3 col-lg-3 col-md-12 col-sm-12 col-12 mb-2">
															<label for="validationCustom04">Site</label>
															<div class="form-group">
																<select class="form-control" id="site" name="site[]" required>
																	<?php
																	$multi_access = false;
																	if ($utilisateur->HasDroits("10_470")) {
																		$multi_access = true;
																	}

																	$site_array = $site_classe->GetAllSiteAccessibleForUser($utilisateur);
																	$deja = false;
																	$nbre_site_ = $site_array->rowCount();

																	$first_site  = $USER_SITE_ID;
																	$sites = $site_array->fetchAll(PDO::FETCH_ASSOC);
																	echo "<option selected value='{$MULTI_ACCESS_SITE_CODE}'>{$MULTI_ACCESS_SITE_LABEL}</option>";

																	foreach ($sites as  $row_) {
																		echo "<option value='{$row_["code_site"]}'>{$row_["intitule_site"]}</option>";
																	}
																	?>
																</select>
															</div>
														</div>

														<!-- 2. Champ CVS (FILTRÉ PAR SITE) -->
														<div class="col-xl-3 col-lg-3 col-md-12 col-sm-12 col-12 mb-2">
															<label for="validationCustomCVS">CVS</label>
															<div class="form-group">
																<select class="form-control" id="cvs" name="cvs[]" multiple>
																	<option value='ALL' selected>Tous les CVS</option>
																</select>
															</div>
														</div>

														<!-- 3. Champ DU -->
														<div class="col-xl-2 col-lg-2 col-md-12 col-sm-12 col-12 mb-2 ">
															<label for="validationCustom05">Du</label>
															<div class="form-group">
																<div class="input-group date">
																	<input type="text" class="form-control datetimepicker-input" name="Du" id="Du" required />
																	<div class="input-group-append">
																		<div class="input-group-text" id="add_on_du"><i class="far fa-calendar-alt"></i></div>
																	</div>
																</div>
															</div>
														</div>

														<!-- 4. Champ AU -->
														<div class="col-xl-2 col-lg-2 col-md-12 col-sm-12 col-12 mb-2 ">
															<label for="validationCustom05">Au</label>
															<div class="form-group">
																<div class="input-group date">
																	<input type="text" class="form-control datetimepicker-input" name="Au" id="Au" required />
																	<div class="input-group-append">
																		<div class="input-group-text" id="add_on_au"><i class="far fa-calendar-alt"></i></div>
																	</div>
																</div>
															</div>
														</div>

														<!-- 5. Bouton EXTRAIRE -->
														<div class="col-xl-2 col-lg-2 col-md-12 col-sm-12 col-12 ">
															<label> </label>
															<p class="text-right">
																<button type="submit" class="btn btn-outline-primary" style="margin-top: 5px;">
																	<i class="fas fa-file-excel mr-2"></i>Extraire Excel
																</button>
															</p>
														</div>

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

						</div>
					<?php } ?>
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
	<?php
	include_once "layout_script.php";
	?>
	<script src="assets/js/select2.min.js"></script>
<script>

	// Initialisation Select2 SITE
	$('#site').select2({
		placeholder: "Sites",
		multiple: true,
		allowClear: true
	});


	// Initialisation Select2 CVS
	$('#cvs').select2({
		placeholder: "Sélectionnez des CVS",
		multiple: true,
		allowClear: true
	});


	jQuery(document).ready(function($) {

		"use strict";


		$("#add_on_du").click(function() {
			$('#Du').datetimepicker('show');
		});


		$("#add_on_au").click(function() {
			$('#Au').datetimepicker('show');
		});


		$('#Du').datetimepicker({
			format: 'dd/mm/yyyy',
			language: 'fr',
			weekStart: 1,
			todayBtn: 1,
			autoclose: 1,
			minView: 2
		});


		$('#Au').datetimepicker({
			format: 'dd/mm/yyyy',
			language: 'fr',
			weekStart: 1,
			todayBtn: 1,
			autoclose: 1,
			minView: 2
		});



		/**
		 * Gestion SITE
		 */
		$('#site').on('change', function() {

			let values = $(this).val() || [];

			let ALL_SITE = "<?php echo $MULTI_ACCESS_SITE_CODE; ?>";


			// Aucun site sélectionné
			if(values.length === 0){

				$(this)
				.val([ALL_SITE])
				.trigger('change.select2');


				loadCVS('ALL');

				return;
			}



			// Si Tous les sites + un site spécifique
			if(values.length > 1 && values.includes(ALL_SITE)){


				values = values.filter(function(value){

					return value !== ALL_SITE;

				});


				$(this)
				.val(values)
				.trigger('change.select2');

			}



			// Tous les sites sélectionné seul
			if(values.length === 1 && values[0] === ALL_SITE){

				loadCVS('ALL');

			}

			else {

				loadCVS(values.join(','));

			}

		});





		/**
		 * Chargement CVS selon SITE
		 */
		function loadCVS(siteCodes){


			$.ajax({

				url: window.location.href,

				type:'POST',

				data:{
					ajax_action:'get_cvs_by_site',
					site_codes:siteCodes
				},

				dataType:'json',


				success:function(response){


					$('#cvs').empty();


					// Toujours ajouter Tous les CVS
					$('#cvs')
					.append('<option value="ALL">Tous les CVS</option>');



					$.each(response,function(index,cvs){

						$('#cvs')
						.append(
							'<option value="'+cvs.code+'">'
							+cvs.libelle+
							'</option>'
						);

					});



					$('#cvs')
					.val(['ALL'])
					.trigger('change.select2');


				},


				error:function(xhr,status,error){

					console.error(
						"Erreur chargement CVS : ",
						error
					);

				}


			});


		}







		/**
		 * Gestion CVS
		 */
		$('#cvs').on('change', function(){


			let values = $(this).val() || [];


			// Aucun CVS sélectionné
			if(values.length === 0){


				$(this)
				.val(['ALL'])
				.trigger('change.select2');


				return;

			}



			// Tous les CVS + CVS spécifique
			if(values.length > 1 && values.includes('ALL')){


				values = values.filter(function(value){

					return value !== 'ALL';

				});


				$(this)
				.val(values)
				.trigger('change.select2');


			}


		});





		/**
		 * Chargement initial
		 */
		let initialSites = $('#site').val();


		if(!initialSites || initialSites.length===0){


			$('#site')
			.val(["<?php echo $MULTI_ACCESS_SITE_CODE; ?>"])
			.trigger('change.select2');


			loadCVS('ALL');


		}

		else {


			if(initialSites.includes("<?php echo $MULTI_ACCESS_SITE_CODE; ?>")){

				loadCVS('ALL');

			}
			else{

				loadCVS(initialSites.join(','));

			}

		}



	});

</script>

</body>

</html>