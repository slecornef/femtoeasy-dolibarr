<?php
/* Copyright (C) 2001-2007	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2005		Eric Seigne				<eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2012	Regis Houssin			<regis.houssin@capnetworks.com>
 * Copyright (C) 2006		Andre Cianfarani		<acianfa@free.fr>
 * Copyright (C) 2011-2019	Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2013-2022	Charlene BENKE			<charlene@patas-monkey.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \file	   htdocs/factory/report.php
 *  \ingroup	factory
 *  \brief	  Page des Ordres de fabrication sur la fiche produit
 */

$res=@include("../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../main.inc.php");		// For "custom" directory

require_once DOL_DOCUMENT_ROOT."/core/lib/product.lib.php";
require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
require_once DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php";
require_once DOL_DOCUMENT_ROOT."/product/stock/class/mouvementstock.class.php";
require_once DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php";

require_once DOL_DOCUMENT_ROOT."/core/class/html.formfile.class.php";
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT."/core/lib/date.lib.php";
require_once DOL_DOCUMENT_ROOT.'/product/class/productbatch.class.php';

dol_include_once('/factory/class/factory.class.php');
dol_include_once('/factory/core/lib/factory.lib.php');

if (! empty($conf->global->FACTORY_ADDON) 
	&& is_readable(dol_buildpath("/factory/core/modules/factory/".$conf->global->FACTORY_ADDON.".php")))
	dol_include_once("/factory/core/modules/factory/".$conf->global->FACTORY_ADDON.".php");


$langs->load("bills");
$langs->load("products");
$langs->load("stocks");
$langs->load("factory@factory");
$langs->load("productbatch");

$id=GETPOST('id', 'int');
$ref=GETPOST('ref', 'alpha');
$action=GETPOST('action', 'alpha');
$confirm=GETPOST('confirm', 'alpha');
$cancel=GETPOST('cancel', 'alpha');
$key=GETPOST('key');
$parent=GETPOST('parent');

$sortfield=GETPOST('sortfield', "alpha");
$sortorder=GETPOST('sortorder', "alpha");

// Security check
if (! empty($user->socid)) $socid=$user->socid;
$fieldvalue = (! empty($id) ? $id : (! empty($ref) ? $ref : ''));
$fieldtype = (! empty($ref) ? 'ref' : 'rowid');
$result = restrictedArea($user, 'factory');

$mesg = '';

$product = new Product($db);
$entrepot = new Entrepot($db);
$factory = new Factory($db);
$form = new Form($db);

$productid=0;
if ($id || $ref) {
	// l'of et le produit associé
	$result = $factory->fetch($id, $ref);
	$result = $product->fetch($factory->fk_product);
	$result = $entrepot->fetch($factory->fk_entrepot);

	$id = $factory->id;
}


// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('factoryreport'));

$parameters = array('product' => $product);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $factory, $action); 
// Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

/*
 * Actions
 */

if (empty($reshook)) {
	if ($action == 'closeof') {
		$ok = true;

		if (!empty($conf->productbatch->enabled)) {

			// Test if all is put ok
			$prods_arbo = $factory->getChildsOF($id);
			$product_chidren = $factory->getChildsArbo(0, $id);

			$error = 0;

			if (count($prods_arbo) > 0) {

				$totprixfabrication = 0;
				$productstatic = new Product($db);
				foreach ($prods_arbo as $value) {

					$used = 0 ;
					$totalused = 0;
					$lost = 0;
					$totallost = 0;

					$productstatic->fetch($value['id']);
					$productstatic->load_stock();
					$batch = new Productbatch($db);
					$details = $productstatic->stock_warehouse[$factory->fk_entrepot]->detail_batch;
					if($productstatic->hasbatch()) {
						$totalused = 0;
						$totallost = 0;
						$i = 0;
						$num = count($details);
						if ($num > 0) {
							while ($i < $num) {
								if (GETPOST("qtyused_" . $value['id'] . "_" . $i)) {
									$used = GETPOST("qtyused_" . $value['id'] . "_" . $i);
									$totalused += $used;
									$lost = GETPOST("qtydeleted_" . $value['id'] . "_" . $i);
									$totallost += $lost;
								}
								$i++;
							}

							foreach ($product_chidren as $valuechildren) {
								if ($value['id'] == $valuechildren[0]) {
									$qtyusedcomponent = $valuechildren[1] * $factory->qty_planned;
								}
							}
							if ($totalused < $qtyusedcomponent) {
								setEventMessages($langs->trans("ErrorFieldNotPut",$productstatic->label), null, 'errors');
								$error++;
							}
						}
					}
				}
			}

			// PLANTE !!!
			//			if ( $product->status_batch == 1 ) {
			//				if (empty(GETPOST("lot_number"))) {
			//					setEventMessages($langs->trans("ErrorLotNotPut"), null, 'errors');
			//					$error++;
			//				}
			//			}

			if ($error) {
				$ok = false;
				$action = '';
			}
		}

		if ($ok==true) {
			
			$factory->qty_made = GETPOST("qtymade");
			$factory->date_end_made = dol_mktime(
				GETPOST('madeendhour', 'int'), GETPOST('madeendmin', 'int'), 0,
				GETPOST('madeendmonth', 'int'), GETPOST('madeendday', 'int'), GETPOST('madeendyear', 'int')
			);
			$factory->duration_made = GETPOST("duration_madehour") * 3600 + GETPOST("duration_mademin") * 60;
			$factory->description = GETPOST("description");

			// si rien de fabriqué le statut de l'of est mis à "annulé"
			if (GETPOST("qtymade") == 0)
				$factory->statut = 9;
			else
				$factory->statut = 2;

			//on mémorise les infos de l'OF
			$sql = "UPDATE " . MAIN_DB_PREFIX . "factory ";
			$sql .= " SET date_end_made = " . ($factory->date_end_made ? "'" . $db->idate($factory->date_end_made) . "'" : 'null');
			$sql .= " , duration_made = " . ($factory->duration_made ? $factory->duration_made : 'null');
			$sql .= " , qty_made = " . ($factory->qty_made ? $factory->qty_made : 'null');
			$sql .= " , description = '" . $db->escape($factory->description) . "'";
			$sql .= " , fk_statut =".$factory->statut;
			$sql .= " WHERE rowid = " . $id;
			if ($db->query($sql)) {

				$lot = GETPOST("lot_number");
				$dDLUO = dol_mktime(
								12, 0, 0, 
								GETPOST('dluo_' . 'month'), 
								GETPOST('dluo_' . 'day'), 
								GETPOST('dluo_' . 'year')
				);
				$dDLC = dol_mktime(
								12, 0, 0, 
								GETPOST('dlc_' . 'month'), 
								GETPOST('dlc_' . 'day'), 
								GETPOST('dlc_' . 'year')
				);

				// on boucle sur les lignes de l'OF
				$prods_arbo = $factory->getChildsOF($id);
				$product_chidren = $factory->getChildsArbo(0, $id);

				$mouvP = new MouvementStock($db);
				$mouvP->origin = new Factory($db);
				$mouvP->origin->id = $id;
				// V15 on passe par l'élément direct
				$mouvP->origin_type = $mouvP->origin->element; // 'factory'
				$mouvP->origin_id = $mouvP->origin->id;

				if (count($prods_arbo) > 0) {
					$totprixfabrication = 0;
					$productstatic = new Product($db);
					foreach ($prods_arbo as $value) {

						$productstatic->fetch($value['id']);
						$productstatic->load_stock();
						if ((!empty($conf->productbatch->enabled)) && $productstatic->hasbatch()) {
							$batch = new Productbatch($db);
							$details = $productstatic->stock_warehouse[$factory->fk_entrepot]->detail_batch;
							$totalused = 0;
							$totallost = 0;
							$i = 0;
							$num = count($details);
							if ($num > 0) {
								while ($i < $num) {
									if (GETPOST("qtyused_" . $value['id'] . "_" . $i)) {
										$used = GETPOST("qtyused_" . $value['id'] . "_" . $i);
										$totalused += $used;
										$lost = GETPOST("qtydeleted_" . $value['id'] . "_" . $i);
										$totallost += $lost;
										foreach ($details as $pdluo) {
											if (GETPOST("batchl" . $value['id'] . "_" . $i) == $pdluo->id) {
												//$used=$used+$lost;
												$batch->fetch($pdluo->id);

												if ($used > 0) {

													$idmv = $mouvP->livraison (
																	$user, $productstatic->id, 
																	$factory->fk_entrepot, $used, 0,
																	$langs->trans("UsedforFactory", $factory->ref), 
																	'', $batch->eatby, $batch->sellby, $batch->batch
													);

													/*$idmv = $productstatic->correct_stock_batch($user, $factory->fk_entrepot, $used, 1,
														$langs->trans("UsedforFactory", $factory->ref), 0,
														$batch->eatby, $batch->sellby, $batch->batch, '', 'factory', $factory->id);*/
												}
												if ($lost > 0) {
													$idmv = $mouvP->livraison(
																	$user, $productstatic->id, 
																	$factory->fk_entrepot, $lost, 0,
																	$langs->trans("DeletedFactory", $factory->ref), 
																	'', $batch->eatby, $batch->sellby, $batch->batch
													);

													/*$idmv = $productstatic->correct_stock_batch($user,$factory->fk_entrepot,$lost,1,
														$langs->trans("DeletedFactory", $factory->ref),0,
														$batch->eatby, $batch->sellby, $batch->batch,'','factory',$factory->id);*/
												}

											}
										}
									}
									$i++;
								}

								// on met à jour les infos des lignes de l'OF
								$sql = "UPDATE " . MAIN_DB_PREFIX . "factorydet ";
								$sql .= " SET qty_used = " . ($totalused ? $totalused : 0);
								$sql .= " , qty_deleted = " . ($totallost ? $totallost : 0);
								//$sql.= " , qty_backwarhoused = ".(GETPOST("qtybackwarhoused_".$value['id']) ? GETPOST("qtybackwarhoused_".$value['id']) : 0);
								$sql .= " WHERE fk_factory = " . $id;
								$sql .= " AND fk_product = " . $value['id'];
								$db->query($sql);
							}

						} else {


							// on détermine la quantité utilisé à partir de la quantité fabriquée
							$qtyusedcomponent = 0;
							foreach ($product_chidren as $valuechildren) {

								if ($value['id'] == $valuechildren[0]) {
									// si il s'agit d'un produit global
									if ($valuechildren[6] == 1) {
										// Si on a rien fabriqu� cela repart en entrep�t
										if ($factory->qty_made != 0)
											$qtyusedcomponent = $valuechildren[1];
									} else
										$qtyusedcomponent = $valuechildren[1] * $factory->qty_made;
								}
							}

							// on met à jour les infos des lignes de l'OF
							$sql = "UPDATE " . MAIN_DB_PREFIX . "factorydet ";
							$sql .= " SET qty_used = " . $qtyusedcomponent;
							$sql .= " , qty_deleted = " . (GETPOST("qtydeleted_" . $value['id']) ? GETPOST("qtydeleted_" . $value['id']) : 0);
							$sql .= " WHERE fk_factory = " . $id;
							$sql .= " AND fk_product = " . $value['id'];
							if ($db->query($sql)) {
								// si les valeurs ne sont pas parfaite (perte ou plus moins fabriqu�), on ajoute des mouvements de stock en plus
								if ((GETPOST("qtydeleted_" . $value['id']) != 0) || ($value['qtyplanned'] != $qtyusedcomponent)) {
									// si il y a du détruit
									if (GETPOST("qtydeleted_" . $value['id']) > 0)
										$idmv = $mouvP->livraison($user, $value['id'], $factory->fk_entrepot,
											GETPOST("qtydeleted_" . $value['id']), 0, // le prix est à 0 pour ne pas impacter le pmp
											$langs->trans("DeletedFactory", $factory->ref), $factory->date_end_made
										);

									// on calcul si il y a du retour en stock (dans un sens ou l'autre
									// on n'enleve pas les quantité supprimé du stock
									//$retourstock = ($value['qtyplanned'] - GETPOST("qtydeleted_".$value['id']) - GETPOST("qtyused_".$value['id']));
									$retourstock = ($value['qtyplanned'] - $qtyusedcomponent);

									// le prix est à 0 pour ne pas impacter le pmp
									if ($retourstock != 0) // on renvoie au stock (attention au sens du mouvement)
										$idmv = $mouvP->livraison(
											$user, $value['id'], $factory->fk_entrepot, (-1 * $retourstock), 0,
											$langs->trans("NotUsedFactory", $factory->ref), $factory->date_end_made
										);
									elseif ($retourstock > 0) // on a utilisé moins que l'on avait, on rend au stock
										$idmv = $mouvP->reception(
											$user, $value['id'], $factory->fk_entrepot, $retourstock, $value['price'],
											$langs->trans("NeedMoreFactory", $factory->ref), $factory->date_end_made
										);

								}
								// on totalise le prix d'achat des composants utilisé pour déterminer un prix de fabrication et mettre à jour le pmp du produit fabriqué
								// attention on prend les quantités utilisé et détruite
								//print "used=".GETPOST("qtyused_".$value['id'])."+del=".GETPOST("qtydeleted_".$value['id'])."*pmp =".$value['pmp']."<br>";
								$totprixfabrication += $qtyusedcomponent * $value['pmp'];
								$totprixfabrication += GETPOST("qtydeleted_" . $value['id']) * $value['pmp'];
							}
						}
					}
				}
				//print "totprixfabrication=".$totprixfabrication."<br>";
				// on ajoute un mouvement de stock d'entr�e de produit
				$idmv = $mouvP->reception($user, $factory->fk_product, $factory->fk_entrepot,
					$factory->qty_made, ($totprixfabrication / $factory->qty_made),
					$langs->trans("BuildedFactory", $factory->ref), $dDLC ? $dDLC : $factory->date_end_made, $dDLUO, $lot
				);

				// Call trigger
				$result = $factory->call_trigger('FACTORY_CLOSE', $user);

			}

			// on redirige pour éviter le doublement
			header("Location: " . $_SERVER["PHP_SELF"] . '?id=' . $factory->id);
			exit;
		}
		//$action="";
	}
	if ($action == 'reopenof') {
		$factory->statut = 1;
		$sql = "UPDATE ".MAIN_DB_PREFIX."factory ";
		$sql.= " SET fk_statut =1";
		$sql.= " WHERE rowid = ".$id;
		if ($db->query($sql)) {
			// on supprimera les mouvements de stock quand le mouvement sera stocké V6?
		}
		$action="";
	}
	if ($action == 'deleteof') {
		// on commence par supprimer les lignes de détails
		$factory->del_componentOF($id);
		// on supprime l'OF
		$factory->deleteOF($id);
		// et on redirige sur la liste des of
		header("Location: list.php");
		exit;

	}
}
/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

$productstatic = new Product($db);

llxHeader("", "", $langs->trans("CardFactory"));

dol_htmloutput_mesg($mesg);

$head=factory_prepare_head($factory, $user);
$titre=$langs->trans("Factory");
$picto="factory@factory";
dol_fiche_head($head, 'factoryreport', $titre, -1, $picto);


if ((int) DOL_VERSION >= 6)
	$urllink='list.php';
else
	$urllink='list-old.php';

$linkback = '<a href="'.$urllink.'?restore_lastsearch_values=1' . (! empty($productid) ? '&productid=' . $productid : '') . '">' . $langs->trans("BackToList") . '</a>';

// factory card
$morehtmlref='<div class="refidno">';
// Ref product
$morehtmlref.='<br>'.$langs->trans('Product') . ' : ' . $product->getNomUrl(1)." - ".$product->label;
if (empty($conf->global->MAIN_DISABLE_OTHER_LINK)) 
	$morehtmlref.=' (<a href="'.$urllink.'?productid='.$factory->fk_product.'">'.$langs->trans("OtherFactory").'</a>)';

// ref storage
// rendre modifiable
$morehtmlref.='<br><table class="nobordernopadding" width="100%"><tr><td>'.$langs->trans("Warehouse").' :</td>';
$morehtmlref.='<td>';
	if ($factory->fk_entrepot >0)
		$morehtmlref.=$entrepot->getNomUrl(1)." - ".$entrepot->lieu." (".$entrepot->zip.")" ;
if (empty($conf->global->MAIN_DISABLE_OTHER_LINK)) 
	$morehtmlref.=' (<a href="'.$urllink.'?entrepotid='.$factory->fk_entrepot.'">'.$langs->trans("OtherFactory").'</a>)';

$morehtmlref.='</td></tr>';
$morehtmlref.='</table>';
$morehtmlref.='</div>';

dol_banner_tab($factory, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<div class="fichecenter">';

print '<form name="closeof" action="'.$_SERVER["PHP_SELF"].'?id='.$factory->id.'" method="post">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="closeof">';

print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border tableforfield" width="100%">';


print '<tr class="liste_titre nodrag nodrop"><th align=left colspan=2>';
print $langs->trans("OFAdditionalInfos");
print '</td></tr>';

// pour gerer le bom que depuis la v11 (stable)
if ((int) DOL_VERSION > 10 && $conf->global->MAIN_MODULE_BOM ) {
	require_once DOL_DOCUMENT_ROOT."/bom/class/bom.class.php";
	$bom_static = new BOM($db);
	$bom_static->fetch($factory->fk_bom);
	print '<tr><td valign=top  >'.$langs->trans("BOMLink").'</td ><td colspan="3" valign=top>';
	if ($factory->fk_bom >0)
		print $bom_static->getNomUrl(1);
	print '</td></tr>';	
}

// Date start planned
print '<tr><td width=20%>'.$langs->trans("FactoryDateStartPlanned").'</td><td width=30%>';
print dol_print_date($factory->date_start_planned, 'day');
print ' - ';
print dol_print_date($factory->date_start_made, 'day');
print '</td></tr>';

// Date end planned
print '<tr><td>'.$langs->trans("FactoryDateEndPlanned").'</td><td>';
print dol_print_date($factory->date_end_planned,'day');
print ' - ';
if ($factory->statut == 1)
	print $form->selectDate(
					($factory->date_end_made ? $factory->date_end_made : $factory->date_end_planned), 
					'madeend', 0, 0, '', "madeend"
	);
else
	print dol_print_date($factory->date_end_made, 'day');
print '</td></tr>';
	
// quantity
print '<tr><td>'.$langs->trans("QuantityPlanned").'</td><td>';
print $factory->qty_planned;
print ' - ';
if ($factory->statut == 1)
	print '<input type="text" name="qtymade" size=6 value="'.($factory->qty_made ? $factory->qty_made : $factory->qty_planned).'">';
else
	print $factory->qty_made;
print '</td></tr>';
	
// duration
print '<tr><td>'.$langs->trans("FactoryDurationPlanned").'</td><td>';
print convertSecondToTime($factory->duration_planned, 'allhourmin');
print ' - ';

if ($factory->statut == 1)
	print $form->select_duration(
					'duration_made', 
					($factory->duration_made ? $factory->duration_made : $factory->duration_planned),
					0, 'text'
	);
else
	print convertSecondToTime($factory->duration_made, 'allhourmin');
print '</td></tr>';

print '<tr><td colspan=2>';
if ($factory->statut == 1)
	print '<textarea name="description" wrap="soft" cols="80" rows="'.ROWS_4.'">'.$factory->description.'</textarea>';
else
	print str_replace(array("\r\n", "\n"), "<br>", $factory->description);
print '</td></tr>';

print '</table>';
print '</div>';
print '<div class="fichehalfright"><div class="ficheaddleft">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border tableforfield" width="100%">';

print '<tr class="liste_titre nodrag nodrop"><th align=left colspan=2>';
print $langs->trans("ProductsAdditionalInfos");
print '</th></tr>';



// TVA
print '<tr><td>'.$langs->trans("VATRate").'</td>';
print '<td>'.vatrate($product->tva_tx.($product->tva_npr?'*':''), true).'</td></tr>';

// Price
print '<tr><td>'.$langs->trans("SellingPrice").'</td><td>';
if ($product->price_base_type == 'TTC') {
	print price($product->price_ttc).' '.$langs->trans($product->price_base_type);
	$sale="";
} else {
	print price($product->price).' '.$langs->trans($product->price_base_type);
	$sale=$product->price;
}
print '</td></tr>';

// Price minimum
print '<tr><td>'.$langs->trans("MinPrice").'</td><td>';
if ($product->price_base_type == 'TTC')
	print price($product->price_min_ttc).' '.$langs->trans($product->price_base_type);
else
	print price($product->price_min).' '.$langs->trans($product->price_base_type);
print '</td></tr>';

// Status (to sell)
print '<tr><td>'.$langs->trans("Status").'</td><td colspan="2">';
print $product->getLibStatut(2, 0);
print '  ';

print $product->getLibStatut(2, 1);
print '</td></tr>';

print '<tr><td>'.$langs->trans("PhysicalStock").'</td>';
$product->load_stock();
print '<td>'.$product->stock_reel.'</td></tr>';

print '</table>';

print '</div>';

print '</div></div>';
print '<div style="clear:both"></div>';

// indique si on a d�j� une composition de pr�sente ou pas
$compositionpresente=0;

$prods_arbo =$factory->getChildsOF($id); 
print load_fiche_titre($langs->trans("FactorisedProductsNumber").' : '.count($prods_arbo),'','');

// List of subproducts
if (count($prods_arbo) > 0) {
	$compositionpresente=1;
	//print '<b>'.$langs->trans("FactoryTableInfo").'</b><BR>';
	print '<table class="border" >';
	print '<tr class="liste_titre">';
	print '<td class="liste_titre" width=100px align="left">'.$langs->trans("Ref").'</td>';
	print '<td class="liste_titre" width=200px align="left">'.$langs->trans("Label").'</td>';
	print '<td class="liste_titre" width=50px align="center">'.$langs->trans("QtyUnitNeed").'</td>';
	print '<td class="liste_titre" width=50px align="center">'.$langs->trans("FactoryQtyPlanned").'</td>';
	print '<td class="liste_titre" width=50px align="center">'.$langs->trans("QtyConsummed").'</td>';
	print '<td class="liste_titre" width=50px align="center">'.$langs->trans("QtyLosed").'</td>';
	print '<td class="liste_titre" width=50px align="center">'.$langs->trans("QtyUsed").'</td>';
	print '<td class="liste_titre" width=50px align="center">'.$langs->trans("QtyRestocked").'</td>';

	print '</tr>';
	$mntTot=0;
	$pmpTot=0;

	foreach ($prods_arbo as $value) {

		// verify if product have child then display it after the product name
		$tmpChildArbo=$factory->getChildsArbo($value['id']);
		$nbChildArbo="";
		if (count($tmpChildArbo) > 0) $nbChildArbo=" (".count($tmpChildArbo).")";

		print '<tr>';
		print '<td align="left">'.$factory->getNomUrlFactory($value['id'], 1,'fiche').$nbChildArbo;
		print $factory->PopupProduct($value['id']);
		print '</td>';
		print '<td align="left" title="'.$value['description'].'">';
		print $value['label'].'</td>';
		print '<td align="center">'.$value['nb'];
		if ($value['globalqty'] == 1)
			print "&nbsp;G";
		print '</td>';
		print '<td align="center">'.($value['qtyplanned']).'</td>';

		$productstatic->fetch($value['id']);
		$productstatic->load_stock();

		if ($factory->statut == 1) {
			// si c'est la premi�re saisie on alimente avec les valeurs par d�faut
			if ($value['qtyused']) {
				if ((! empty($conf->productbatch->enabled)) && $productstatic->hasbatch()) {
					$subj=0;
					//print '<input name="idl'.$indiceAsked.'" type="hidden" value="'.$line->id.'">';
					foreach ($productstatic->stock_warehouse[1]->detail_batch as $dbatch) {
						//var_dump($dbatch);
						$substock=$dbatch->qty +0 ;
						print '<tr><td colspan="4" >';
						print $langs->trans("Batch").' '.$dbatch->batch.' ('.$dbatch->qty.")";
						print '</br>';
						if ($dbatch->eatby)
							print $langs->trans("EatByDate").' : '.dol_print_date($dbatch->eatby,"day");
						if ($dbatch->sellby)
							print $langs->trans("SellByDate").' : '.dol_print_date($dbatch->sellby,"day");


						print '</td><td align="center">';
						print '<input name="qtyused_'.$value['id'].'_'.$subj.'" id="qtyused_'.$value['id'].'_'.$subj.'" type="text" size="4" value="'.min($value['qtyused'],$substock).'">';
						print '<input name="batchl'.$value['id'].'_'.$subj.'" type="hidden" value="'.$dbatch->id.'">';
						print '</td>';

						print '</td><td align="center">';
						print '<input name="qtydeleted_'.$value['id'].'_'.$subj.'" id="qtydeleted_'.$value['id'].'_'.$subj.'" type="text" size="4" value="">';
						print '<input name="batchldel'.$value['id'].'_'.$subj.'" type="hidden" value="'.$dbatch->id.'">';
						print '</td>';

						print '<td ></td>';
						print '<td ></td>';

						if ($defaultqty<=0)
							$defaultqty=0;
						else
							$defaultqty -=min($defaultqty,$substock);

						$subj++;
					}
				}
				else {


					print '<td align="right">' . $value['qtyused'] . '</td>';
					print '<td align="center">';
					print '<input type=text size=4 name="qtydeleted_' . $value['id'] . '"  value="' . ($value['qtydeleted']) . '"></td>';
				}
				print '<td align="right">' . ($value['qtyused'] + $value['qtydeleted']) . '</td>';
				print '<td align="right">' . ($value['qtyplanned'] - ($value['qtyused'] + $value['qtydeleted'])) . '</td>';

			} else {

				if ((! empty($conf->productbatch->enabled)) && $productstatic->hasbatch())
				{
					$subj=0;
					//print '<input name="idl'.$indiceAsked.'" type="hidden" value="'.$line->id.'">';
					foreach ($productstatic->stock_warehouse[1]->detail_batch as $dbatch)
					{
						//var_dump($dbatch);
						$substock=$dbatch->qty +0 ;
						print '<tr><td colspan="4" >';
						print $langs->trans("Batch").' '.$dbatch->batch.' ('.$dbatch->qty.')';
						print '</br>';
						if ($dbatch->eatby)
							print $langs->trans("EatByDate").' : '.dol_print_date($dbatch->eatby,"day");
						if ($dbatch->sellby)
							print $langs->trans("SellByDate").' : '.dol_print_date($dbatch->sellby,"day");

						print '</td><td align="center">';
						print '<input name="qtyused_'.$value['id'].'_'.$subj.'" id="qtyused_'.$value['id'].'_'.$subj.'" type="text" size="4" value="'.min($value['qtyused'],$substock).'">';
						print '<input name="batchl'.$value['id'].'_'.$subj.'" type="hidden" value="'.$dbatch->id.'">';
						print '</td>';

						print '</td><td align="center">';
						print '<input name="qtydeleted_'.$value['id'].'_'.$subj.'" id="qtydeleted_'.$value['id'].'_'.$subj.'" type="text" size="4" value="">';
						print '<input name="batchldel'.$value['id'].'_'.$subj.'" type="hidden" value="'.$dbatch->id.'">';
						print '</td>';

						print '<td ></td>';
						print '<td ></td>';


						if ($defaultqty<=0) {
							$defaultqty=0;
						} else {
							$defaultqty -=min($defaultqty,$substock);
						}
						$subj++;
					}
				}
				else {
					print '<td align="center">' . $value['qtyplanned'] . '</td>';
					print '<td align="center"><input type=text size=4 name="qtydeleted_' . $value['id'] . '"  value="0"></td>';
					print '<td ></td>';
					print '<td ></td>';
				}
			}
		} else {
			print '<td align="right">'.$value['qtyused'].'</td>'; 
			print '<td align="right">'.$value['qtydeleted'].'</td>'; 
			print '<td align="right">'.($value['qtyused']+$value['qtydeleted']).'</td>'; 
			print '<td align="right">'.($value['qtyplanned']-($value['qtyused']+$value['qtydeleted'])).'</td>'; 
		}
		print '</tr>';
	}
	print '</table>';
}
print '</td>';
print '</tr></table>';

$parameters = array( 'colspan' => ' colspan="3"');
// Note that $action and $object may have been modified by
$reshook = $hookmanager->executeHooks('formObjectOptions', $parameters, $factory, $action); 

/* Barre d'action		*/
if ($action == '') {
	if ( !(empty($conf->productbatch->enabled)) && $product->status_batch==1) {
		if ($user->rights->factory->creer && $factory->statut == 1) {
			print load_fiche_titre($langs->trans("FactoryBatchLotNumber"),'','');
			print "<table class='noborder' width='50%'>";
			print '<tr class="liste_titre">';
			print '<td>'.$langs->trans("EatByDate").'</td>';
			print '<td>'.$langs->trans("SellByDate").'</td>';
			print '<td>'.$langs->trans("BatchNumber").'</td>';
			print "</tr>\n";

			print '<tr>';
			print '<td>';
			print $form->selectDate('','dlc_','','',1,"");
			print '</td><td>';
			print $form->selectDate('','dluo_','','',1,"");
			print '</td><td>';
			print '<input type="text" name="lot_number" size="40" value="">';
			print '</td>';
			//print '<td colspan="2">&nbsp</td>';
			print '</tr>';
			print "</table>";
		}
	}
	print '<div class="tabsAction">';
	if ($user->rights->factory->creer && $factory->statut == 1)
		print '<input type=submit class="butAction" value="'.$langs->trans("CloseFactory").'">';
	print '</div>';
}

print '</form>';


print '<br><hr><br>';
print load_fiche_titre($langs->trans("FactoryMovement"), '', '');

// list des mouvements associés à l'of

$sql = "SELECT p.rowid, p.ref as product_ref, p.label as produit, p.fk_product_type as type,";
if ((int) DOL_VERSION < 7)
	$sql.= " e.label as stock,";
else
	$sql.= " e.ref as stock,";
$sql.= " e.rowid as entrepot_id, e.lieu,";
$sql.= " m.rowid as mid, m.value, m.datem, m.batch, m.label, m.fk_origin, m.origintype";
//$sql.= ", m.inventorycode, m.batch, m.eatby, m.sellby";
$sql.= " FROM (".MAIN_DB_PREFIX."entrepot as e,";
$sql.= " ".MAIN_DB_PREFIX."product as p,";
$sql.= " ".MAIN_DB_PREFIX."stock_mouvement as m)";
$sql.= " WHERE m.fk_product = p.rowid";
$sql.= " AND m.fk_entrepot = e.rowid";
$sql.= " AND e.entity IN (".getEntity('stock', 1).")";
if (empty($conf->global->STOCK_SUPPORTS_SERVICES)) $sql.= " AND p.fk_product_type = 0";
$sql.= " AND m.fk_origin = ".$id;
$sql.= " AND m.origintype = 'factory'";

$sql.= $db->order($sortfield, $sortorder);

//print $sql;

$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	
	$productstatic=new Product($db);

	$param='';
	if ($id) $param.='&id='.$id;
	print '<table class="noborder" width="100%">';
	print "<tr class='liste_titre'>";
	print_liste_field_titre($langs->trans("Date"), $_SERVER["PHP_SELF"], "m.datem", "", $param, "", $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("ProductRef"), $_SERVER["PHP_SELF"], "p.ref", "", $param, "", $sortfield, $sortorder);
	if ( !(empty($conf->productbatch->enabled)) && $product->status_batch==1) 
		print_liste_field_titre($langs->trans("Batch"), $_SERVER["PHP_SELF"], "m.batch", "", $param, "", $sortfield, $sortorder);

	print_liste_field_titre($langs->trans("LabelMovement"), $_SERVER["PHP_SELF"], "m.label", "", $param, "", $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Units"), $_SERVER["PHP_SELF"], "m.value", "", $param, 'align="right"', $sortfield, $sortorder);
	print "</tr>\n";

	$arrayofuniqueproduct=array();
	$i=0;
	while ($i < $num) {
		$objp = $db->fetch_object($resql);
		print "<tr >";
		print '<td>'.dol_print_date($db->jdate($objp->datem), 'dayhour').'</td>';
		// Product ref
		print '<td>';
		$productstatic->fetch($objp->rowid);
		print $productstatic->getNomUrl(1, '', 16);
		print "</td>\n";

		// batch
		if ( !(empty($conf->productbatch->enabled)) && $product->status_batch==1) 
			print '<td>'.$objp->batch.'</td>';

		// Label of movement
		print '<td>'.$objp->label.'</td>';
		// Value
		print '<td align="right">';
		if ($objp->value > 0) print '+';
		print $objp->value.'</td>';
		print "</tr>\n";
		$i++;
	}
	$db->free($resql);
	print "</table></form><br>";
}

// si on est dans les conditions possible d'une suppression
print '<div class="tabsAction">';
if ($user->rights->factory->creer && $factory->statut == 0 && $num == 0)
	print '<a class="butAction" href="report.php?action=deleteof&token='.newtoken().'&id='.$id.'">'.$langs->trans("DeleteFactory").'</a>';
print '</div>';


llxFooter();
$db->close();