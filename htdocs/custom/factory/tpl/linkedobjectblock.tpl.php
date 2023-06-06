<?php
/* Copyright (C) 2014-2020		Charlene Benke	<charlie@patas-monkey.com>
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
 *
 */

 // Protection to avoid direct call of template
if (empty($conf) || !is_object($conf))
{
	print "Error, template page can't be called as URL";
	exit;
}


print '<!-- BEGIN PHP TEMPLATE -->';

$langs = $GLOBALS['langs'];
$db = $GLOBALS['db'];
$linkedObjectBlock = $GLOBALS['linkedObjectBlock'];

$langs->load("factory@factory");


require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
$productlink = new Product($db);
require_once DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php";
$entrepotlink = new Entrepot($db);

if (false) {
	print '<tr class="liste_titre">';
	print '<td align="left" >'.$langs->trans("Type").'</td>';
	print '<td align="left" width=25% >'.$langs->trans("Ref").'</td>';
	print '<td align="left" width=25% >'.$langs->trans("Warehouse").' / '.$langs->trans("Product").'</td>';
	print '<td align="left" width=25% >'.$langs->trans("Date").'</td>';
	print '<td align="left" width=25% >'.$langs->trans("Qty").'</td>';
	print '<td align="left" colspan=2 >'.$langs->trans("Status").'</td>';
	print '</tr>';
}

foreach ($linkedObjectBlock as $key => $objectlink) {

	$productlink->fetch($objectlink->fk_product);
	$entrepotlink->fetch($objectlink->fk_entrepot);

	print '<tr class="oddeven" >';
	print '<td valign=top>'.$langs->trans("Factory").'</td>';
	print '<td valign=top align="left">'.$objectlink->getNomUrl(1).'</td>';
	
	print '<td align="left">'.$entrepotlink->getNomUrl(1).'<br>'.$productlink->getNomUrl(1).'</td>';
	print '<td valign=top align="center">';
	if ($objectlink->status == 0)
		print dol_print_date($objectlink->date_start_planned, 'day'); 
	if ($objectlink->status == 1)
		print dol_print_date($objectlink->date_start_made, 'day'); 
	if ($objectlink->status == 2)
		print dol_print_date($objectlink->date_end_made, 'day'); 
	print '</td>';

	print '<td valign=top align="left">';
	print $langs->trans("Planned")." : ".$objectlink->qty_planned; 
	if ($objectlink->status > 1)
		print "<br>".$langs->trans("Made")." : ".$objectlink->qty_made; 
	print '</td>';
	print '<td align="right">'.$objectlink->getLibStatut(3).'</td>';
	print '<td class="right"><a class="reposition" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=dellink&dellinkid='.$key.'">';
	print img_picto($langs->transnoentitiesnoconv("RemoveLink"), 'unlink').'</a></td>';
	print '</tr>'."\n";
}

print '<!-- END PHP TEMPLATE -->'."\n";