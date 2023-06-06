<?php
/* Copyright (C) 2015-2022		Charlene Benke		<charlene@patas-monkey.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 *	\file	   htdocs/factory/index.php
 *  \ingroup	factory
 *  \brief	  Page accueil de factory
 */

$res=@include("../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../main.inc.php");		// For "custom" directory


dol_include_once('/factory/class/factory.class.php');
dol_include_once('/factory/core/lib/factory.lib.php');

if ($user->socid) $socid=$user->socid;

$result = restrictedArea($user, 'factory', '', 'factory', '', 'fk_soc_client');
$langs->load("factory@factory");

$factory_static = new Factory($db);

/*
 * View
 */

$transAreaType = $langs->trans("FactoryArea");
$helpurl='EN:Module_Factory|FR:Module_Factory|ES:M&oacute;dulo_Factory';

llxHeader("", $transAreaType, $helpurl);

print load_fiche_titre($transAreaType, '', "cubes");

print '<table border="0" width="100%" class="notopnoleftnoright">';
print '<tr><td valign="top" width="30%" class="notopnoleft">';


$prodser = array();
$factoryStatus[0] = $factoryStatus[1] = $factoryStatus[2] = $factoryStatus[3] = 0;

$sql = "SELECT f.fk_statut, COUNT(f.rowid) as total, SUM(f.qty_made) as totalmade ";
$sql.= " FROM ".MAIN_DB_PREFIX."factory as f";
$sql.= ' WHERE f.entity IN ('.getEntity('factory', 1).')';
$sql.= " GROUP BY f.fk_statut";
$result = $db->query($sql);

print '<div class="div-table-responsive-no-min">';
if ($conf->use_javascript_ajax) {
	while ($objp = $db->fetch_object($result)){
		$factoryStatus[$objp->fk_statut] = $objp->total;
	}
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans("Statistics").'</th></tr>';
	print '<tr><td class="center nopaddingleftimp nopaddingrightimp">';

	$SommeA = $factoryStatus[0];
	$SommeB = $factoryStatus[1];
	$SommeC = $factoryStatus[2];
	$SommeD = $factoryStatus[3];

	$total = 0;
	$dataval = array();
	$datalabels = array();
	$i = 0;

	$total = $SommeA + $SommeB + $SommeC + $SommeD;
	$dataseries = array();

	$dataseries[] = array($langs->transnoentities("Draft"), round($SommeA));
	$dataseries[] = array($langs->transnoentities("Validate"), round($SommeB));
	$dataseries[] = array($langs->transnoentities("Close"), round($SommeC));
	$dataseries[] = array($langs->transnoentities("Cancel"), round($SommeD));

	include_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
	$dolgraph = new DolGraph();
	$dolgraph->SetData($dataseries);
	$dolgraph->setShowLegend(2);
	$dolgraph->setShowPercent(0);
	$dolgraph->SetType(array('pie'));
	$dolgraph->setHeight('200');
	$dolgraph->draw('idgraphstatus');
	print $dolgraph->show($total ? 0 : 1);

	print '</td></tr>';
	print '</table>';
} else {
	/*
	* Factory par statut
	*/

	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre"><td >'.$langs->trans("FactoryStatutRepart").'</td>';
	print '<td align=right>'.$langs->trans("Nb").'</td>';
	print '<td align=right>'.$langs->trans("Qty").'</td></tr>';

	$result = $db->query($sql);
	$statProducts="";
	$total=0;
	$totalmade=0;
	while ($objp = $db->fetch_object($result)) {
		$statProducts.= "<tr >";
		$statProducts.= '<td><a href="list.php?search_status='.$objp->fk_statut.'">';
		$statProducts.= ($objp->fk_statut ? $factory_static->LibStatut($objp->fk_statut):$langs->trans("None"));
		$statProducts.= '</a></td><td align="right">'.price($objp->totalmade, 0, '', 0, 0, 0).'</td>';
		$statProducts.= '<td align="right">'.price($objp->total, 0, '', 0, 0, 0).'</td>';
		$statProducts.= "</tr>";
		$totalmade=$totalmade+$objp->totalmade;
		$total=$total+$objp->total;
	}
	print $statProducts;
	print '<tr class="liste_total"><td>'.$langs->trans("Total").'</td>';
	print '<td align="right">'.price($totalmade, 0, '', 0, 0, 0).'</td>';
	print '<td align="right">'.price($total, 0, '', 0, 0, 0).'</td>';
	print '</tr></table>';
}
print '</div>';

/*
 * nb OF Factory par produits
 */
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre"><td >'.$langs->trans("OFByProductRepart").'</td>';
print '<td align=right>'.$langs->trans("Nb").'</td>';
print '<td align=right>'.$langs->trans("Qty").'</td></tr>';

$max=10;
$sql = "SELECT f.fk_product, p.ref, COUNT(f.rowid) as total, SUM(f.qty_made) as totalmade";
$sql.= " FROM ".MAIN_DB_PREFIX."factory as f, ".MAIN_DB_PREFIX."product as p";
$sql.= ' WHERE f.entity IN ('.getEntity("product", 1).')';
$sql.= ' AND f.fk_product = p.rowid';
$sql.= " GROUP BY f.fk_product, p.ref";
$sql.= ' order by total desc';
$sql.= $db->plimit($max, 0);

$result = $db->query($sql);

$statProducts="";
$total=0;
$totalmade=0;
if ($result) {
	while ($objp = $db->fetch_object($result)) {
		$statProducts.= "<tr >";
		$statProducts.= '<td><a href="list.php?search_product='.$objp->ref.'">'.$objp->ref.'</a></td>';
		$statProducts.= '<td align="right">'.price($objp->total, 0, '', 0, 0, 0).'</td>';
		$statProducts.= '<td align="right">'.price($objp->totalmade, 0, '', 0, 0, 0).'</td>';
		$statProducts.= "</tr>";
		$total=$total+$objp->total;
		$totalmade=$totalmade+$objp->totalmade;
	}
	print $statProducts;
}
print '<tr class="liste_total"><td>'.$langs->trans("Total").'</td>';
print '<td align="right">'.price($total, 0, '', 0, 0, 0).'</td>';
print '<td align="right">'.price($totalmade, 0, '', 0, 0, 0).'</td></tr>';
print '</table>';

print '<br>';
/*
 * nb OF Factory par entrepot
 */
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre"><td >'.$langs->trans("OFByStorageRepart").'</td>';
print '<td align=right>'.$langs->trans("Nb").'</td>';
print '<td align=right>'.$langs->trans("Qty").'</td></tr>';

if ((int) DOL_VERSION < 7)
	$sql.= " e.label as stock, e.rowid as entrepot_id, e.lieu,";
else
	$sql.= " e.ref as stock, e.rowid as entrepot_id, e.lieu,";

if ((int) DOL_VERSION < 7) {
	$sql = "SELECT f.fk_entrepot, e.label as ref, COUNT(f.rowid) as total, SUM(f.qty_made) as totalmade";
	$sql.= " FROM ".MAIN_DB_PREFIX."factory as f, ".MAIN_DB_PREFIX."entrepot as e";
	$sql.= ' WHERE f.entity IN ('.getEntity("product", 1).')';
	$sql.= ' AND f.fk_entrepot = e.rowid';
	$sql.= " GROUP BY f.fk_entrepot, e.label";
} else {
	$sql = "SELECT f.fk_entrepot, e.ref as ref, COUNT(f.rowid) as total, SUM(f.qty_made) as totalmade";
	$sql.= " FROM ".MAIN_DB_PREFIX."factory as f, ".MAIN_DB_PREFIX."entrepot as e";
	$sql.= ' WHERE f.entity IN ('.getEntity("product", 1).')';
	$sql.= ' AND f.fk_entrepot = e.rowid';
	$sql.= " GROUP BY f.fk_entrepot, e.ref";	
}
$sql.= ' order by total desc';
$sql.= $db->plimit($max, 0);


$result = $db->query($sql);

$statProducts="";
$total=0;
$totalmade=0;
if ($result) {
	while ($objp = $db->fetch_object($result)) {
		$statProducts.= "<tr >";
		$statProducts.= '<td><a href="list.php?entrepotid='.$objp->fk_entrepot.'">'.$objp->ref.'</a></td>';
		$statProducts.= '<td align="right">'.price($objp->total, 0, '', 0, 0, 0).'</td>';
		$statProducts.= '<td align="right">'.price($objp->totalmade, 0, '', 0, 0, 0).'</td>';
		$statProducts.= "</tr>";
		$total=$total+$objp->total;
		$totalmade=$totalmade+$objp->totalmade;
	}
	print $statProducts;
}
print '<tr class="liste_total"><td>'.$langs->trans("Total").'</td>';
print '<td align="right">'.price($total, 0, '', 0, 0, 0).'</td>';
print '<td align="right">'.price($totalmade, 0, '', 0, 0, 0).'</td></tr>';
print '</table>';

print '</td><td valign="top" width="70%" class="notopnoleftnoright">';


if (!empty($conf->barcode->enabled))
print '<form method="post" action="list.php">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<table class="noborder" width="100%">';
print "<tr class='liste_titre'>";
print '<td >'.$langs->trans("SearchByRef").'</td>';
if (!empty($conf->barcode->enabled))
	print '<td >'.$langs->trans("SearchByBarCode").'</td>';
print '<td colspan="2">'.$langs->trans("SearchByProduct").'</td>';
print '</tr>';
print "<tr>";
print "<td>".'<input class="flat" type="text" size="14" name="sref"></td>';
if (!empty($conf->barcode->enabled)) {
	print "<td>".'<input class="flat" type="text" size="14" name="sbarcode"></td>';
}
print '<td><input class="flat" type="text" size="14" name="srefproduit"></td>';
print '<td><input type="submit" class="button" value="'.$langs->trans("Search").'"></td>';

print '</tr>';
print "</table></form><br>";


/*
 * Last modified OF
 */
$max=20;
$sql = "SELECT f.rowid, f.ref, p.ref as refproduit, f.qty_planned, f.qty_made, p.label,";
$sql.= " f.tms as datem, f.fk_statut";
$sql.= " FROM ".MAIN_DB_PREFIX."factory as f";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p on f.fk_product = p.rowid";
$sql.= " WHERE f.entity IN (".getEntity("product", 1).")";
$sql.= $db->order("f.tms", "DESC");
$sql.= $db->plimit($max, 0);

//print $sql;
$result = $db->query($sql);
if ($result) {
	$num = $db->num_rows($result);
	$i = 0;
	if ($num > 0) {
		$lastModifiedFactory = $langs->trans("LastFactory", $max);
		print '<table class="noborder" width="100%">';
		$colnb=5;
		print '<tr class="liste_titre"><td colspan="'.$colnb.'">'.$lastModifiedFactory.'</td></tr>';
		while ($i < $num) {
			$objp = $db->fetch_object($result);
			print "<tr>";
			print '<td nowrap="nowrap">';
			$factory_static->fetch($objp->rowid);
			//$equipement_static->ref=$objp->ref;
			//$equipement_static->fk_product=$objp->refproduit;
			print $factory_static->getNomUrl(1);
			print "</td>\n";

			print '<td>'.$objp->refproduit. " - ".dol_trunc($objp->label, 32).'</td>';
			print "<td align=right>".price($objp->qty_made?$objp->qty_made:$objp->qty_planned, 0, '', 0, 0, 0)."</td>";
			print "<td align=center>".dol_print_date($db->jdate($objp->datem), 'day')."</td>";
			print "<td align=right>".$factory_static->LibStatut($objp->fk_statut, 5)."</td>";
			print "</tr>\n";
			$i++;
		}
		print "</table>";
	}
} else
	dol_print_error($db);

print '</td></tr></table>';
llxFooter();
$db->close();