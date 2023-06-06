<?php
/* Copyright (C) 2014-2022	Charlene Benke		<charlene@patas-monkey.com> 
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
 *	  \file	   htdocs/factory/admin/factorydet_extrafields.php
 *		\ingroup	factory
 *		\brief	  Page to setup extra fields of factorydet
 */

$res=@include("../../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../../main.inc.php");		// For "custom" directory


require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
dol_include_once('/factory/core/lib/factory.lib.php');

$langs->load("companies");
$langs->load("admin");
$langs->load("factory@factory");

$extrafields = new ExtraFields($db);
$form = new Form($db);

// List of supported format
$tmptype2label=ExtraFields::$type2label;
$type2label=array('');
foreach ($tmptype2label as $key => $val) $type2label[$key]=$langs->trans($val);

$action=GETPOST('action', 'alpha');
$attrname=GETPOST('attrname', 'alpha');
$elementtype='product_factory';

if (!$user->admin) accessforbidden();

/*
 * Actions
 */
//depending on dolibarr version
if (file_exists($_SERVER['DOCUMENT_ROOT'].'/core/admin_extrafields.inc.php'))
	require DOL_DOCUMENT_ROOT.'/core/admin_extrafields.inc.php';
else
	require DOL_DOCUMENT_ROOT.'/core/actions_extrafields.inc.php';


/*
 * View
 */

$page_name = $langs->trans('FactorySetup') ." - ". $langs->trans("ProductFactoryExtrafields");
$tab = $langs->trans("Factory");

$help_url='https://wiki.patas-monkey.com/index.php?title=Factory#Cr.C3.A9ation_de_champs_suppl.C3.A9mentaires';
llxHeader('', $page_name, $help_url);


$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($page_name, $linkback, 'title_setup');


$head = factory_admin_prepare_head();

dol_fiche_head($head, 'attributesproduct', $tab, -1, 'factory@factory');


print $langs->trans('DefineHereComplementaryAttributes', $tab).'<br>'."\n";
print '<br>';

dol_htmloutput_errors($mesg);

// Load attribute label
$extrafields->fetch_name_optionals_label($elementtype);

print '<table summary="listofattributes" class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Label").'</td>';
print '<td>'.$langs->trans("AttributeCode").'</td>';
print '<td>'.$langs->trans("Type").'</td>';
print '<td align="right">'.$langs->trans("Size").'</td>';
print '<td align="center">'.$langs->trans("Unique").'</td>';
print '<td align="center">'.$langs->trans("Required").'</td>';
print '<td width="80">&nbsp;</td>';
print "</tr>\n";

if (!empty($extrafields->attributes[$elementtype]['type'])) {
	foreach ($extrafields->attributes[$elementtype]['type'] as $key => $value) {
		print "<tr >";
		print "<td>".$extrafields->attributes[$elementtype]['label'][$key]."</td>\n";
		print "<td>".$key."</td>\n";
		print "<td>".$type2label[$extrafields->attributes[$elementtype]['type'][$key]]."</td>\n";
		print '<td align="right">'.$extrafields->attributes[$elementtype]['size'][$key]."</td>\n";
		print '<td align="center">'.yn($extrafields->attributes[$elementtype]['unique'][$key])."</td>\n";
		print '<td align="center">'.yn($extrafields->attributes[$elementtype]['required'][$key])."</td>\n";
		print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'&attrname='.$key.'">'.img_edit().'</a>';
		print "&nbsp; <a href='".$_SERVER["PHP_SELF"]."?action=delete&token=".newToken()."&attrname=$key'>".img_delete()."</a></td>\n";
		print "</tr>";
	}
}

print "</table>";
dol_fiche_end();

// Buttons
if ($action != 'create' && $action != 'edit') {
	print '<div class="tabsAction">';
	print "<a class='butAction' href='".$_SERVER["PHP_SELF"]."?action=create'>".$langs->trans("NewAttribute")."</a>";
	print "</div>";
}


/* ************************************************************************** */
/*																			*/
/* Creation d'un champ optionnel
 /*																			*/
/* ************************************************************************** */

if ($action == 'create') {
	print "<br>";
	print_titre($langs->trans('NewAttribute'));
	require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_add.tpl.php';
}

/* ************************************************************************** */
/*																			*/
/* Edition d'un champ optionnel											   */
/*																			*/
/* ************************************************************************** */
if ($action == 'edit' && ! empty($attrname)) {
	print "<br>";
	print_titre($langs->trans("FieldEdition", $attrname));
	require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_edit.tpl.php';
}

llxFooter();
$db->close();